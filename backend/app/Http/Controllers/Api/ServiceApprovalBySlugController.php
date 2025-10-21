<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedService;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceApprovalBySlugController extends Controller
{
    /**
     * Display pending services for admin approval
     * This is what the frontend calls at /api/admin/services/pending
     */
    public function index(Request $request)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (!$adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            // Get all pending service requests
            $pendingRequests = ServiceRequest::pendingReview()
                ->with(['publisher'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Transform data to match frontend expectations
            $services = $pendingRequests->map(function ($request) {
                return [
                    'id' => $request->id,
                    'slug' => Str::slug($request->name . '-' . $request->id),
                    'name' => $request->name,
                    'description' => $request->description,
                    'type' => $this->mapMethodToType($request->method),
                    'authType' => $this->mapAuthType($request->auth_type),
                    'schedule' => 'office', // Default schedule
                    'monthlyLimit' => $request->max_requests_per_month,
                    'updatedAt' => $request->updated_at,
                    'url' => $request->url,
                    'department' => $request->publisher->department ?? 'No asignado',
                    'category' => 'API REST',
                    'owner' => $request->publisher->name ?? 'Sin responsable',
                    'coverage' => 'Nacional',
                    'documentationUrl' => $request->url,
                    'usage' => 0,
                    'tags' => ['nuevo', 'pendiente'],
                    'labels' => ['revision'],
                    'versions' => [[
                        'id' => 1,
                        'version' => $request->version,
                        'status' => 'draft',
                        'releaseDate' => $request->created_at,
                        'compatibility' => 'Pendiente',
                        'requestable' => true,
                        'limitSuggestion' => $request->max_requests_per_month,
                        'documentation' => $request->url,
                        'notes' => $request->documentation ?? 'Sin notas adicionales'
                    ]]
                ];
            });

            return response()->json($services);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch pending services',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve service by slug
     * This is what the frontend calls at /api/admin/services/{slug}/approve
     */
    public function approve(Request $request, $slug_param)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (!$adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            // Extract ID from slug (assuming format: name-{id})
            $id = $this->extractIdFromSlug($slug_param);
            
            if (!$id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid service identifier',
                ], 422);
            }

            DB::beginTransaction();

            try {
                $serviceRequest = ServiceRequest::findOrFail($id);

                if (!$serviceRequest->isPending()) {
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
                    'review_notes' => $request->review_notes ?? 'Service approved',
                    'approved_service_id' => $service->id,
                ]);

                DB::commit();

                return response()->json([
                    'status' => 'success',
                    'message' => 'Service request approved successfully',
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
     * Reject service by slug
     * This is what the frontend calls at /api/admin/services/{slug}/reject
     */
    public function reject(Request $request, $slug_param)
    {
        try {
            $adminId = $this->resolveAdminId($request);

            if (!$adminId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for admin actions',
                ], 401);
            }

            // Extract ID from slug
            $id = $this->extractIdFromSlug($slug_param);
            
            if (!$id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid service identifier',
                ], 422);
            }

            $serviceRequest = ServiceRequest::findOrFail($id);

            if (!$serviceRequest->isPending()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service request has already been reviewed',
                ], 422);
            }

            $serviceRequest->update([
                'status' => 'rejected',
                'reviewed_by' => $adminId,
                'reviewed_at' => now(),
                'review_notes' => $request->review_notes ?? 'Service rejected by admin',
                'rejection_reason' => $request->rejection_reason ?? 'Service does not meet requirements',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Service request rejected successfully',
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
     * Create a service from an approved service request
     */
    private function createServiceFromRequest(ServiceRequest $serviceRequest, int $adminId): EnhancedService
    {
        $serviceData = [
            'name' => $serviceRequest->name,
            'description' => $serviceRequest->description,
            'url' => $serviceRequest->url,
            'method' => $serviceRequest->method,
            'status' => 'ready_to_publish',
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

    /**
     * Extract numeric ID from slug
     */
    private function extractIdFromSlug($slug)
    {
        // Assuming slug format: name-{id}
        $parts = explode('-', $slug);
        $lastPart = end($parts);
        
        if (is_numeric($lastPart)) {
            return (int) $lastPart;
        }
        
        return null;
    }

    /**
     * Map HTTP method to service type
     */
    private function mapMethodToType($method)
    {
        $mapping = [
            'GET' => 'api-rest',
            'POST' => 'api-rest',
            'PUT' => 'api-rest',
            'PATCH' => 'api-rest',
            'DELETE' => 'api-rest',
        ];

        return $mapping[$method] ?? 'api-rest';
    }

    /**
     * Map auth type for frontend display
     */
    private function mapAuthType($authType)
    {
        $mapping = [
            'oauth' => 'OAuth 2.0',
            'api_key' => 'API Key',
            'token' => 'Token',
            'none' => 'Sin autenticación',
        ];

        return $mapping[$authType] ?? $authType;
    }

    private function resolveAdminId(Request $request): ?int
    {
        return $request->user()?->id ?? Auth::id() ?? 1; // Default to user ID 1 for testing
    }
}