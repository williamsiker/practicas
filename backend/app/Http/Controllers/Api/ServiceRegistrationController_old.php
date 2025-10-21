<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceRegistrationController extends Controller
{
    /**
     * Get all approved services ready for publication by the authenticated publisher
     * PHASE 3: Publisher views their approved services
     */
    public function index(Request $request)
    {
        try {
            // Mock publisher ID - replace with auth when implemented
            $publisherId = 1;

            $query = EnhancedService::byPublisher($publisherId)
                ->with(['approver', 'sourceRequest'])
                ->whereIn('status', ['ready_to_publish', 'published', 'active'])
                ->orderBy('created_at', 'desc');

            // Apply search filter if provided
            if ($request->has('search') && ! empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Apply status filter if provided
            if ($request->has('status') && ! empty($request->status)) {
                $query->where('status', $request->status);
            }

            $perPage = min($request->get('per_page', 10), 50);
            $services = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $services,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch approved services',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get service details
     */
    public function show($id)
    {
        try {
            $service = $this->findServiceById($id);

            if (! $service) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

            // Get versions
            $versions = $this->getServiceVersions($id);
            $service['versions'] = $versions;

            return response()->json([
                'status' => 'success',
                'data' => $service,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch service details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update service
     */
    public function update(Request $request, $id)
    {
        try {
            $service = $this->findServiceById($id);

            if (! $service) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

            $validator = $this->validateServiceData($request->all(), $id);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $serviceData = $this->prepareServiceData($request->all());
            $serviceData['status'] = 'pending'; // Reset to pending after update

            $updatedService = $this->updateServiceRecord($id, $serviceData);

            return response()->json([
                'status' => 'success',
                'message' => 'Service updated successfully and pending approval',
                'data' => $updatedService,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update service',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new version of service
     */
    public function createVersion(Request $request, $id)
    {
        try {
            $service = $this->findServiceById($id);

            if (! service) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'version_number' => 'required|string|max:50',
                'version_description' => 'nullable|string',
                'endpoint_url' => 'required|url',
                'changelog' => 'nullable|array',
                'breaking_changes' => 'nullable|array',
                'parameters' => 'nullable|array',
                'responses' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Check if version already exists
            if ($this->versionExists($id, $request->version_number)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Version already exists for this service',
                ], 422);
            }

            $versionData = [
                'service_id' => $id,
                'version_number' => $request->version_number,
                'version_description' => $request->version_description,
                'changelog' => $request->changelog ? json_encode($request->changelog) : null,
                'breaking_changes' => $request->breaking_changes ? json_encode($request->breaking_changes) : null,
                'endpoint_url' => $request->endpoint_url,
                'parameters' => $request->parameters ? json_encode($request->parameters) : null,
                'responses' => $request->responses ? json_encode($request->responses) : null,
                'status' => 'active',
                'is_default' => $request->is_default ?? false,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $version = $this->createVersionRecord($versionData);

            return response()->json([
                'status' => 'success',
                'message' => 'Service version created successfully',
                'data' => $version,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create service version',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Admin approval/rejection endpoints
     */
    public function approve(Request $request, $id)
    {
        try {
            $service = $this->findServiceById($id);

            if (! $service) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

            $updateData = [
                'status' => 'active',
                'approved_by' => 1, // Mock admin ID
                'approved_at' => now(),
                'approval_notes' => $request->notes,
                'updated_at' => now(),
            ];

            $this->updateServiceRecord($id, $updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Service approved successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to approve service',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function reject(Request $request, $id)
    {
        try {
            $service = $this->findServiceById($id);

            if (! $service) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

            $updateData = [
                'status' => 'rejected',
                'approved_by' => 1, // Mock admin ID
                'approved_at' => now(),
                'rejection_reason' => $request->reason ?? 'No reason provided',
                'updated_at' => now(),
            ];

            $this->updateServiceRecord($id, $updateData);

            return response()->json([
                'status' => 'success',
                'message' => 'Service rejected successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reject service',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Private helper methods using static data for demo

    private function getServicesQuery()
    {
        // Mock data for demo - in real implementation this would query enhanced_services table
        return collect([
            [
                'id' => 1,
                'name' => 'User Authentication API',
                'description' => 'Comprehensive user authentication and authorization service',
                'url' => 'https://api.example.com/auth',
                'method' => 'POST',
                'status' => 'pending',
                'version' => '1.0.0',
                'publisher_id' => 1,
                'requires_auth' => true,
                'auth_type' => 'token',
                'has_demo' => true,
                'demo_url' => 'https://demo.example.com/auth',
                'base_price' => 25.00,
                'max_requests_per_day' => 10000,
                'max_requests_per_month' => 300000,
                'terms_accepted' => true,
                'terms_accepted_at' => now()->subDays(1),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDay(),
            ],
            [
                'id' => 2,
                'name' => 'Payment Processing API',
                'description' => 'Secure payment processing with multiple gateway support',
                'url' => 'https://api.example.com/payments',
                'method' => 'POST',
                'status' => 'active',
                'version' => '2.1.0',
                'publisher_id' => 1,
                'requires_auth' => true,
                'auth_type' => 'api_key',
                'has_demo' => false,
                'base_price' => 0.50,
                'max_requests_per_day' => 5000,
                'max_requests_per_month' => 150000,
                'approved_by' => 1,
                'approved_at' => now()->subDays(5),
                'terms_accepted' => true,
                'terms_accepted_at' => now()->subDays(7),
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(5),
            ],
        ]);
    }

    private function findServiceById($id)
    {
        $services = $this->getServicesQuery();

        return $services->firstWhere('id', $id);
    }

    private function serviceNameExists($name, $excludeId = null)
    {
        $services = $this->getServicesQuery();
        $existing = $services->where('name', $name);

        if ($excludeId) {
            $existing = $existing->where('id', '!=', $excludeId);
        }

        return $existing->isNotEmpty();
    }

    private function validateServiceData($data, $excludeId = null)
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
            'terms_accepted' => 'required|accepted',
        ];

        return Validator::make($data, $rules);
    }

    private function prepareServiceData($data)
    {
        return [
            'name' => $data['name'],
            'description' => $data['description'],
            'url' => $data['url'],
            'method' => $data['method'],
            'status' => 'pending', // Always pending for new services
            'version' => $data['version'] ?? '1.0.0',
            'publisher_id' => 1, // Mock publisher ID
            'requires_auth' => $data['requires_auth'] ?? false,
            'auth_type' => $data['auth_type'] ?? 'none',
            'auth_config' => isset($data['auth_config']) ? json_encode($data['auth_config']) : null,
            'documentation' => $data['documentation'],
            'parameters' => isset($data['parameters']) ? json_encode($data['parameters']) : null,
            'responses' => isset($data['responses']) ? json_encode($data['responses']) : null,
            'error_codes' => isset($data['error_codes']) ? json_encode($data['error_codes']) : null,
            'validations' => isset($data['validations']) ? json_encode($data['validations']) : null,
            'metrics_enabled' => $data['metrics_enabled'] ?? false,
            'metrics_config' => isset($data['metrics_config']) ? json_encode($data['metrics_config']) : null,
            'has_demo' => $data['has_demo'] ?? false,
            'demo_url' => $data['demo_url'] ?? null,
            'base_price' => $data['base_price'] ?? 0,
            'pricing_tiers' => isset($data['pricing_tiers']) ? json_encode($data['pricing_tiers']) : null,
            'max_requests_per_day' => $data['max_requests_per_day'] ?? 1000,
            'max_requests_per_month' => $data['max_requests_per_month'] ?? 30000,
            'features' => isset($data['features']) ? json_encode($data['features']) : null,
            'terms_accepted' => $data['terms_accepted'] ?? false,
            'terms_accepted_at' => $data['terms_accepted'] ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function createServiceRecord($data)
    {
        // Mock creation - in real implementation this would insert to database
        $data['id'] = rand(1000, 9999);

        return $data;
    }

    private function updateServiceRecord($id, $data)
    {
        // Mock update - in real implementation this would update database
        $data['id'] = $id;
        $data['updated_at'] = now();

        return $data;
    }

    private function createInitialVersion($serviceId, $data)
    {
        $versionData = [
            'service_id' => $serviceId,
            'version_number' => $data['version'] ?? '1.0.0',
            'version_description' => 'Initial version',
            'endpoint_url' => $data['url'],
            'parameters' => isset($data['parameters']) ? json_encode($data['parameters']) : null,
            'responses' => isset($data['responses']) ? json_encode($data['responses']) : null,
            'status' => 'active',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return $this->createVersionRecord($versionData);
    }

    private function createVersionRecord($data)
    {
        // Mock creation - in real implementation this would insert to service_versions table
        $data['id'] = rand(1000, 9999);

        return $data;
    }

    private function getServiceVersions($serviceId)
    {
        // Mock versions data
        return [
            [
                'id' => 1,
                'service_id' => $serviceId,
                'version_number' => '1.0.0',
                'version_description' => 'Initial release',
                'status' => 'deprecated',
                'is_default' => false,
                'endpoint_url' => 'https://api.example.com/v1/service',
                'deprecated_at' => now()->subMonths(2),
                'created_at' => now()->subMonths(6),
            ],
            [
                'id' => 2,
                'service_id' => $serviceId,
                'version_number' => '2.0.0',
                'version_description' => 'Major update with breaking changes',
                'status' => 'active',
                'is_default' => true,
                'endpoint_url' => 'https://api.example.com/v2/service',
                'created_at' => now()->subMonths(2),
            ],
        ];
    }

    private function versionExists($serviceId, $versionNumber)
    {
        $versions = $this->getServiceVersions($serviceId);

        return collect($versions)->contains('version_number', $versionNumber);
    }
}
