<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedService;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceApprovalController extends Controller
{
    /**
     * Display a listing of all service requests for admin review.
     * PHASE 2: Admin views all pending requests
     */
    public function index(Request $request)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (! $adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            $query = ServiceRequest::with(['publisher', 'reviewer'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status') && ! empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('publisher_id') && ! empty($request->publisher_id)) {
                $query->where('publisher_id', $request->publisher_id);
            }

            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('publisher', function ($pub) use ($search) {
                            $pub->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            }

            // Get statistics
            $stats = [
                'pending_review' => ServiceRequest::pendingReview()->count(),
                'approved' => ServiceRequest::approved()->count(),
                'rejected' => ServiceRequest::rejected()->count(),
                'needs_modification' => ServiceRequest::needsModification()->count(),
            ];

            $perPage = min($request->get('per_page', 15), 100);
            $serviceRequests = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $serviceRequests,
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch service requests for review',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified service request for detailed review.
     */
    public function show(Request $request, $id)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (! $adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            $serviceRequest = ServiceRequest::with(['publisher', 'reviewer', 'approvedService'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $serviceRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service request not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Approve a service request and create the actual service.
     * PHASE 2: Admin approves and creates service
     */
    public function approve(Request $request, $id)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (! $adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'review_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            try {
                $serviceRequest = ServiceRequest::findOrFail($id);

                if (! $serviceRequest->isPending()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This service request has already been reviewed',
                    ], 422);
                }

                // Check if service name already exists in enhanced_services table
                if (EnhancedService::where('name', $serviceRequest->name)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'A service with this name already exists',
                    ], 422);
                }

                // Create the actual service from the request
                $service = $this->createServiceFromRequest($serviceRequest, $adminId);

                // Update service request status
                $serviceRequest->update([
                    'status' => 'approved',
                    'reviewed_by' => $adminId,
                    'reviewed_at' => now(),
                    'review_notes' => $request->review_notes,
                    'approved_service_id' => $service->id,
                ]);

                DB::commit();

                // Load relationships for response
                $serviceRequest->load(['reviewer', 'approvedService']);

                // TODO: Send notification to publisher

                return response()->json([
                    'status' => 'success',
                    'message' => 'Service request approved and service created successfully',
                    'data' => [
                        'service_request' => $serviceRequest,
                        'created_service' => $service,
                    ],
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve service request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject a service request.
     * PHASE 2: Admin rejects request
     */
    public function reject(Request $request, $id)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (! $adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string|min:20|max:1000',
                'review_notes' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $serviceRequest = ServiceRequest::findOrFail($id);

            if (! $serviceRequest->isPending()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service request has already been reviewed',
                ], 422);
            }

            $serviceRequest->update([
                'status' => 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'review_notes' => $request->review_notes,
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Load relationships for response
            $serviceRequest->load(['reviewer']);

            // TODO: Send notification to publisher

            return response()->json([
                'status' => 'success',
                'message' => 'Service request rejected successfully',
                'data' => $serviceRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject service request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Request modifications to a service request.
     * PHASE 2: Admin requests changes
     */
    public function requestModifications(Request $request, $id)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (! $adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'review_notes' => 'required|string|min:20|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $serviceRequest = ServiceRequest::findOrFail($id);

            if (! $serviceRequest->isPending()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service request has already been reviewed',
                ], 422);
            }

            $serviceRequest->update([
                'status' => 'needs_modification',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'review_notes' => $request->review_notes,
            ]);

            // Load relationships for response
            $serviceRequest->load(['reviewer']);

            // TODO: Send notification to publisher

            return response()->json([
                'status' => 'success',
                'message' => 'Modification request sent to publisher successfully',
                'data' => $serviceRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to request modifications',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get pending service requests count for admin dashboard
     */
    public function getPendingCount()
    {
        try {
            $count = ServiceRequest::pendingReview()->count();

            return response()->json([
                'status' => 'success',
                'data' => ['pending_count' => $count],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch pending count',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a service from an approved service request
     */
    private function createServiceFromRequest(ServiceRequest $serviceRequest, int $adminId): EnhancedService
    {
        // Map service request data to enhanced service model
        $serviceData = [
            'name' => $serviceRequest->name,
            'description' => $serviceRequest->description,
            'url' => $serviceRequest->url,
            'method' => $serviceRequest->method,
            'status' => 'ready_to_publish', // Service is created but not yet published
            'version' => $serviceRequest->version,
            'publisher_id' => $serviceRequest->publisher_id,
            'source_request_id' => $serviceRequest->id,
            'requires_auth' => $serviceRequest->requires_auth,
            'auth_type' => $serviceRequest->auth_type,
            'auth_config' => $serviceRequest->auth_config,
            'documentation' => $serviceRequest->documentation,
            'parameters' => $serviceRequest->parameters,
            'responses' => $serviceRequest->responses,
            'error_codes' => $serviceRequest->error_codes,
            'validations' => $serviceRequest->validations,
            'metrics_enabled' => $serviceRequest->metrics_enabled,
            'metrics_config' => $serviceRequest->metrics_config,
            'has_demo' => $serviceRequest->has_demo,
            'demo_url' => $serviceRequest->demo_url,
            'base_price' => $serviceRequest->base_price,
            'pricing_tiers' => $serviceRequest->pricing_tiers,
            'max_requests_per_day' => $serviceRequest->max_requests_per_day,
            'max_requests_per_month' => $serviceRequest->max_requests_per_month,
            'features' => $serviceRequest->features,
            'approved_by' => $adminId,
            'approved_at' => now(),
            'terms_accepted' => $serviceRequest->terms_accepted,
            'terms_accepted_at' => $serviceRequest->terms_accepted_at,
        ];

        return EnhancedService::create($serviceData);
    }

    private function resolveAdminId(Request $request): ?int
    {
        return $request->user()?->id ?? Auth::id();
    }
}
