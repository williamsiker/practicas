<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

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
     * Display the specified approved service
     * PHASE 3: Publisher views service details
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

            $service = EnhancedService::byPublisher($publisherId)
                ->with(['approver', 'sourceRequest', 'publishedBy'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $service,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Service not found or access denied',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update service operational configuration
     * PHASE 3: Publisher configures service before publication
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

            $service = EnhancedService::byPublisher($publisherId)->findOrFail($id);

            if (! $service->canBeConfigured()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service cannot be configured in its current status',
                ], 422);
            }

            $validator = $this->validateOperationalConfig($request->all());

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $operationalConfig = $this->prepareOperationalConfig($request->all(), $publisherId);

            $existingConfig = $service->operational_config ?? [];
            if (! is_array($existingConfig)) {
                $existingConfig = [];
            }

            $mergedConfig = array_merge($existingConfig, $operationalConfig);

            $service->update([
                'operational_config' => $mergedConfig,
                'updated_at' => now(),
            ]);

            $service->load(['approver', 'sourceRequest', 'publishedBy']);

            return response()->json([
                'status' => 'success',
                'message' => 'Service configuration updated successfully',
                'data' => $service,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update service configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Publish service to consumers
     * PHASE 3: Publisher publishes service to make it available to consumers
     */
    public function publish(Request $request, $id)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $service = EnhancedService::byPublisher($publisherId)->findOrFail($id);

            if (! $service->canBePublished()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service cannot be published in its current status',
                ], 422);
            }

            // Optional: validate any additional publication settings
            $validator = Validator::make($request->all(), [
                'publication_notes' => 'nullable|string|max:500',
                'notify_consumers' => 'boolean',
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
                $service->update([
                    'status' => 'published',
                    'published_at' => now(),
                    'published_by' => $publisherId,
                ]);

                // TODO: Add service to catalog for consumers
                // TODO: Send notifications if requested

                DB::commit();

                $service->load(['approver', 'sourceRequest', 'publishedBy']);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Service published successfully and is now available to consumers',
                    'data' => $service,
                ]);
            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to publish service',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Unpublish service from consumers
     * PHASE 3: Publisher temporarily removes service from public access
     */
    public function unpublish(Request $request, $id)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $service = EnhancedService::byPublisher($publisherId)->findOrFail($id);

            if (! $service->isPublished()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This service is not currently published',
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'unpublish_reason' => 'required|string|min:10|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $operationalConfig = $service->operational_config ?? [];
            $operationalConfig['unpublish_reason'] = $request->unpublish_reason;
            $operationalConfig['unpublished_at'] = now()->toISOString();

            $service->update([
                'status' => 'ready_to_publish', // Return to ready state
                'operational_config' => $operationalConfig,
            ]);

            // TODO: Remove from consumer catalog
            // TODO: Send notifications to active consumers

            $service->load(['approver', 'sourceRequest', 'publishedBy']);

            return response()->json([
                'status' => 'success',
                'message' => 'Service unpublished successfully and removed from consumer access',
                'data' => $service,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to unpublish service',
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
            $publisherId = $this->resolvePublisherId($request);

            if (! $publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required for publisher actions',
                ], 401);
            }

            $service = EnhancedService::byPublisher($publisherId)->findOrFail($id);

            if (! $service->isPublished()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Can only create versions for published services',
                ], 422);
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

            // TODO: Check if version already exists in service_versions table
            // TODO: Create version record in service_versions table

            return response()->json([
                'status' => 'success',
                'message' => 'Service version created successfully - functionality to be implemented',
                'data' => [],
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
     * Validate operational configuration data
     */
    private function validateOperationalConfig($data)
    {
        $rules = [
            'schedule_config' => 'nullable|array',
            'schedule_config.enabled' => 'boolean',
            'schedule_config.timezone' => 'nullable|string',
            'schedule_config.available_hours' => 'nullable|array',
            'rate_limits' => 'nullable|array',
            'rate_limits.requests_per_minute' => 'nullable|integer|min:1',
            'rate_limits.requests_per_hour' => 'nullable|integer|min:1',
            'rate_limits.requests_per_day' => 'nullable|integer|min:1',
            'access_control' => 'nullable|array',
            'access_control.allowed_offices' => 'nullable|array',
            'access_control.blocked_offices' => 'nullable|array',
            'access_control.ip_whitelist' => 'nullable|array',
            'notification_settings' => 'nullable|array',
            'notification_settings.error_alerts' => 'boolean',
            'notification_settings.usage_alerts' => 'boolean',
            'monitoring_config' => 'nullable|array',
        ];

        return Validator::make($data, $rules);
    }

    /**
     * Prepare operational configuration data
     */
    private function prepareOperationalConfig($data, int $publisherId)
    {
        return [
            'schedule_config' => $data['schedule_config'] ?? null,
            'rate_limits' => $data['rate_limits'] ?? null,
            'access_control' => $data['access_control'] ?? null,
            'notification_settings' => $data['notification_settings'] ?? null,
            'monitoring_config' => $data['monitoring_config'] ?? null,
            'configured_at' => now()->toISOString(),
            'configured_by' => $publisherId,
        ];
    }

    private function resolvePublisherId(Request $request): ?int
    {
        return $request->user()?->id ?? Auth::id();
    }
}
