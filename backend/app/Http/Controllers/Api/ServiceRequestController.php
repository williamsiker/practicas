<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ServiceRequestController extends Controller
{
    /**
     * Display a listing of service requests for the authenticated publisher.
     */
    public function index(Request $request)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $query = ServiceRequest::byPublisher($publisherId)
                ->with(['reviewer'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if ($request->has('status') && ! empty($request->status)) {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $perPage = min($request->get('per_page', 10), 50);
            $serviceRequests = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $serviceRequests,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch service requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created service request.
     * PHASE 1: Publisher submits service request
     */
    public function store(Request $request)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $validator = $this->validateServiceRequestData($request->all());

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check for unique service name
            if (ServiceRequest::where('name', $request->name)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A service request with this name already exists',
                ], 422);
            }

            $serviceRequestData = $this->prepareServiceRequestData($request->all(), $publisherId);
            $serviceRequest = ServiceRequest::create($serviceRequestData);

            // Load relationships for response
            $serviceRequest->load(['publisher']);

            return response()->json([
                'status' => 'success',
                'message' => 'Service request submitted successfully and is pending admin review',
                'data' => $serviceRequest,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit service request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified service request.
     */
    public function show(Request $request, $id)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $serviceRequest = ServiceRequest::byPublisher($publisherId)
                ->with(['reviewer', 'approvedService'])
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
     * Update the specified service request.
     * Only allowed if status is 'pending_review' or 'needs_modification'
     */
    public function update(Request $request, $id)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $serviceRequest = ServiceRequest::byPublisher($publisherId)->findOrFail($id);

            if (! $serviceRequest->canBeModified()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service request cannot be modified in its current status',
                ], 422);
            }

            $validator = $this->validateServiceRequestData($request->all(), $id);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check for unique service name (excluding current request)
            if (ServiceRequest::where('name', $request->name)
                ->where('id', '!=', $id)
                ->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A service request with this name already exists',
                ], 422);
            }

            $updateData = $this->prepareServiceRequestData($request->all(), $publisherId);
            $updateData['status'] = 'pending_review'; // Reset status to pending
            $updateData['reviewed_by'] = null;
            $updateData['reviewed_at'] = null;
            $updateData['review_notes'] = null;
            $updateData['rejection_reason'] = null;

            $serviceRequest->update($updateData);
            $serviceRequest->load(['publisher']);

            return response()->json([
                'status' => 'success',
                'message' => 'Service request updated successfully and is pending admin review',
                'data' => $serviceRequest,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update service request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified service request from storage.
     * Only allowed if status is 'pending_review', 'rejected', or 'needs_modification'
     */
    public function destroy(Request $request, $id)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $serviceRequest = ServiceRequest::byPublisher($publisherId)->findOrFail($id);

            if ($serviceRequest->isApproved() || $serviceRequest->hasApprovedService()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot delete an approved service request that has been created as a service',
                ], 422);
            }

            $serviceRequest->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Service request deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete service request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate service request data
     */
    private function validateServiceRequestData($data, $excludeId = null)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'url' => 'required|url',
            'method' => 'required|in:GET,POST,PUT,DELETE,PATCH',
            'version' => 'required|string|max:50',
            'requires_auth' => 'boolean',
            'auth_type' => 'required_if:requires_auth,true|in:none,token,api_key,oauth',
            'auth_config' => 'nullable|array',
            'documentation' => 'required|string|min:100',
            'parameters' => 'nullable|array',
            'responses' => 'nullable|array',
            'error_codes' => 'nullable|array',
            'validations' => 'nullable|array',
            'metrics_enabled' => 'boolean',
            'metrics_config' => 'nullable|array',
            'has_demo' => 'boolean',
            'demo_url' => 'nullable|required_if:has_demo,true|url',
            'base_price' => 'required|numeric|min:0',
            'pricing_tiers' => 'nullable|array',
            'max_requests_per_day' => 'required|integer|min:1',
            'max_requests_per_month' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'justification' => 'required|string|min:50|max:2000',
            'terms_accepted' => 'required|accepted',
        ];

        return Validator::make($data, $rules);
    }

    /**
     * Prepare service request data for storage
     */
    private function prepareServiceRequestData($data, int $publisherId)
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'],
            'url' => $data['url'],
            'method' => $data['method'],
            'version' => $data['version'] ?? '1.0.0',
            'requires_auth' => $data['requires_auth'] ?? false,
            'auth_type' => $data['auth_type'] ?? 'none',
            'auth_config' => $data['auth_config'] ?? null,
            'documentation' => $data['documentation'],
            'parameters' => $data['parameters'] ?? null,
            'responses' => $data['responses'] ?? null,
            'error_codes' => $data['error_codes'] ?? null,
            'validations' => $data['validations'] ?? null,
            'metrics_enabled' => $data['metrics_enabled'] ?? false,
            'metrics_config' => $data['metrics_config'] ?? null,
            'has_demo' => $data['has_demo'] ?? false,
            'demo_url' => $data['demo_url'] ?? null,
            'base_price' => $data['base_price'] ?? 0,
            'pricing_tiers' => $data['pricing_tiers'] ?? null,
            'max_requests_per_day' => $data['max_requests_per_day'] ?? 1000,
            'max_requests_per_month' => $data['max_requests_per_month'] ?? 30000,
            'features' => $data['features'] ?? null,
            'justification' => $data['justification'],
            'terms_accepted' => $data['terms_accepted'] ?? false,
            'terms_accepted_at' => $data['terms_accepted'] ? now() : null,
            'status' => 'pending_review',
            'publisher_id' => $publisherId,
        ];
    }

    private function resolvePublisherId(Request $request): ?int
    {
        return $request->user()?->id ?? Auth::id();
    }
}
