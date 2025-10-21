<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedService;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PublisherServiceController extends Controller
{
    /**
     * Display services for publisher
     * This is what the frontend calls at /api/publicador/services
     */
    public function index(Request $request)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (!$publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required',
                ], 401);
            }

            // Get both service requests and enhanced services for this publisher
            $serviceRequests = ServiceRequest::byPublisher($publisherId)
                ->orderBy('created_at', 'desc')
                ->get();

            $enhancedServices = EnhancedService::byPublisher($publisherId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Transform service requests to match frontend format
            $services = collect();

            // Add service requests (pending, rejected, etc.)
            foreach ($serviceRequests as $request) {
                $metrics = $request->metrics_config ?? [];
                $services->push([
                    'id' => $request->id,
                    'slug' => Str::slug($request->name . '-' . $request->id),
                    'name' => $request->name,
                    'description' => $request->description,
                    'url' => $request->url,
                    'type' => $this->mapMethodToType($request->method),
                    'status' => $this->mapStatus($request->status),
                    'typeLabel' => $this->mapTypeLabel($this->mapMethodToType($request->method)),
                    'authType' => $this->mapAuthType($request->auth_type),
                    'authTypeLabel' => $this->mapAuthLabel($request->auth_type),
                    'schedule' => Arr::get($metrics, 'schedule', 'office'),
                    'monthlyLimit' => $request->max_requests_per_month,
                    'currentVersion' => $request->version,
                    'updatedAt' => $request->updated_at,
                    'versions' => [[
                        'version' => $request->version,
                        'status' => 'draft',
                        'releaseDate' => $request->created_at,
                        'compatibility' => 'Pendiente',
                        'requestable' => true,
                        'limitSuggestion' => $request->max_requests_per_month,
                        'notes' => $request->documentation ?? 'Sin notas'
                    ]]
                ]);
            }

            // Add enhanced services (approved, published, etc.)
            foreach ($enhancedServices as $service) {
                $metrics = $service->metrics_config ?? [];
                $operational = $service->operational_config ?? [];
                $services->push([
                    'id' => $service->id,
                    'slug' => Str::slug($service->name . '-' . $service->id),
                    'name' => $service->name,
                    'description' => $service->description,
                    'url' => $service->url,
                    'type' => $this->mapMethodToType($service->method),
                    'status' => $this->mapEnhancedStatus($service->status),
                    'typeLabel' => $this->mapTypeLabel($this->mapMethodToType($service->method)),
                    'authType' => $this->mapAuthType($service->auth_type),
                    'authTypeLabel' => $this->mapAuthLabel($service->auth_type),
                    'schedule' => Arr::get($operational, 'schedule')
                        ?? Arr::get($metrics, 'schedule', 'office'),
                    'monthlyLimit' => $service->max_requests_per_month,
                    'currentVersion' => $service->version,
                    'updatedAt' => $service->updated_at,
                    'versions' => [[
                        'version' => $service->version,
                        'status' => 'available',
                        'releaseDate' => $service->approved_at ?? $service->created_at,
                        'compatibility' => 'Compatible',
                        'requestable' => true,
                        'limitSuggestion' => $service->max_requests_per_month,
                        'notes' => $service->documentation ?? 'Sin notas'
                    ]]
                ]);
            }

            return response()->json($services);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch services',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new service request
     * This is what the frontend calls when submitting the form
     */
    public function store(Request $request)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (!$publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required',
                ], 401);
            }

            $validator = $this->validateServiceData($request->all());

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

            // Transform to match frontend expectations
            $responseData = [
                'id' => $serviceRequest->id,
                'slug' => Str::slug($serviceRequest->name . '-' . $serviceRequest->id),
                'name' => $serviceRequest->name,
                'description' => $serviceRequest->description,
                'url' => $serviceRequest->url,
                'type' => $this->mapMethodToType($serviceRequest->method),
                'status' => $this->mapStatus($serviceRequest->status),
                'typeLabel' => $this->mapTypeLabel($this->mapMethodToType($serviceRequest->method)),
                'authType' => $this->mapAuthType($serviceRequest->auth_type),
                'authTypeLabel' => $this->mapAuthLabel($serviceRequest->auth_type),
                'schedule' => Arr::get($serviceRequest->metrics_config, 'schedule', 'office'),
                'monthlyLimit' => $serviceRequest->max_requests_per_month,
                'currentVersion' => $serviceRequest->version,
                'updatedAt' => $serviceRequest->updated_at,
                'versions' => [[
                    'version' => $serviceRequest->version,
                    'status' => 'draft',
                    'releaseDate' => $serviceRequest->created_at,
                    'compatibility' => 'Pendiente',
                    'requestable' => true,
                    'limitSuggestion' => $serviceRequest->max_requests_per_month,
                    'notes' => $serviceRequest->documentation ?? 'Sin notas'
                ]]
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Service request submitted successfully',
                'data' => $responseData,
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
     * Duplicate an existing service
     */
    public function duplicate(Request $request, $slug)
    {
        try {
            $publisherId = $this->resolvePublisherId($request);

            if (!$publisherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Authentication required',
                ], 401);
            }

            // Extract ID from slug
            $id = $this->extractIdFromSlug($slug);
            
            if (!$id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid service identifier',
                ], 422);
            }

            // Try to find the service in either table
            $originalService = ServiceRequest::where('id', $id)
                ->where('publisher_id', $publisherId)
                ->first();

            if (!$originalService) {
                $originalService = EnhancedService::where('id', $id)
                    ->where('publisher_id', $publisherId)
                    ->first();
            }

            if (!$originalService) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

            // Create duplicate service request
            $duplicateMetrics = $originalService->metrics_config ?? $originalService->operational_config ?? [];

            $duplicateData = [
                'name' => $originalService->name . ' (Copia)',
                'description' => $originalService->description,
                'url' => $originalService->url,
                'method' => $originalService->method,
                'version' => '1.0.0',
                'requires_auth' => $originalService->requires_auth,
                'auth_type' => $originalService->auth_type,
                'auth_config' => $originalService->auth_config,
                'documentation' => $originalService->documentation,
                'parameters' => $originalService->parameters,
                'responses' => $originalService->responses,
                'error_codes' => $originalService->error_codes,
                'validations' => $originalService->validations,
                'metrics_enabled' => $originalService->metrics_enabled,
                'metrics_config' => $duplicateMetrics,
                'has_demo' => $originalService->has_demo,
                'demo_url' => $originalService->demo_url,
                'base_price' => $originalService->base_price,
                'pricing_tiers' => $originalService->pricing_tiers,
                'max_requests_per_day' => $originalService->max_requests_per_day,
                'max_requests_per_month' => $originalService->max_requests_per_month,
                'features' => $originalService->features,
                'justification' => 'Duplicado del servicio: ' . $originalService->name,
                'terms_accepted' => true,
                'terms_accepted_at' => now(),
                'status' => 'pending_review',
                'publisher_id' => $publisherId,
            ];

            $duplicate = ServiceRequest::create($duplicateData);

            // Transform to match frontend expectations
            $responseData = [
                'id' => $duplicate->id,
                'slug' => Str::slug($duplicate->name . '-' . $duplicate->id),
                'name' => $duplicate->name,
                'description' => $duplicate->description,
                'url' => $duplicate->url,
                'type' => $this->mapMethodToType($duplicate->method),
                'status' => 'borrador',
                'typeLabel' => $this->mapTypeLabel($this->mapMethodToType($duplicate->method)),
                'authType' => $this->mapAuthType($duplicate->auth_type),
                'authTypeLabel' => $this->mapAuthLabel($duplicate->auth_type),
                'schedule' => Arr::get($duplicate->metrics_config, 'schedule', 'office'),
                'monthlyLimit' => $duplicate->max_requests_per_month,
                'currentVersion' => $duplicate->version,
                'updatedAt' => $duplicate->updated_at,
                'versions' => [[
                    'version' => $duplicate->version,
                    'status' => 'draft',
                    'releaseDate' => $duplicate->created_at,
                    'compatibility' => 'Pendiente',
                    'requestable' => true,
                    'limitSuggestion' => $duplicate->max_requests_per_month,
                    'notes' => $duplicate->documentation ?? 'Sin notas'
                ]]
            ];

            return response()->json([
                'status' => 'success',
                'message' => 'Service duplicated successfully',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to duplicate service',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate service data
     */
    private function validateServiceData($data)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'short_description' => 'required|string|max:1000',
            'url' => 'required|url',
            'type' => 'required|string',
            'auth_type' => 'required|string',
            'schedule' => 'required|string|in:office,full',
            'monthly_limit' => 'nullable|integer|min:0',
            'terms_accepted' => 'required|accepted',
            'version.version' => 'required|string|max:50',
        ];

        return Validator::make($data, $rules);
    }

    /**
     * Prepare service request data
     */
    private function prepareServiceRequestData($data, $publisherId)
    {
        $monthlyLimit = isset($data['monthly_limit']) && $data['monthly_limit'] !== null
            ? (int) $data['monthly_limit']
            : 0;
        $maxRequestsPerMonth = max($monthlyLimit, 0);

        $metricsConfig = [
            'schedule' => $data['schedule'] ?? 'office',
            'monthly_limit' => $monthlyLimit,
        ];

        return [
            'name' => $data['name'],
            'description' => $data['short_description'],
            'url' => $data['url'],
            'method' => $this->mapTypeToMethod($data['type']),
            'version' => $data['version']['version'] ?? '1.0.0',
            'requires_auth' => $data['auth_type'] !== 'ninguna',
            'auth_type' => $this->mapFrontendAuthType($data['auth_type']),
            'documentation' => $data['short_description'],
            'base_price' => 0,
            'max_requests_per_day' => 1000,
            'max_requests_per_month' => $maxRequestsPerMonth,
            'metrics_enabled' => $monthlyLimit > 0,
            'metrics_config' => $metricsConfig,
            'justification' => 'Servicio solicitado desde plataforma PIDE',
            'terms_accepted' => $data['terms_accepted'],
            'terms_accepted_at' => now(),
            'status' => 'pending_review',
            'publisher_id' => $publisherId,
        ];
    }

    /**
     * Map frontend type to HTTP method
     */
    private function mapTypeToMethod($type)
    {
        $mapping = [
            'api-rest' => 'GET',
            'form-web' => 'POST',
            'archivo-batch' => 'POST',
            'proceso-manual' => 'POST',
        ];

        return $mapping[$type] ?? 'GET';
    }

    /**
     * Map HTTP method to service type
     */
    private function mapMethodToType($method)
    {
        $mapping = [
            'GET' => 'api-rest',
            'POST' => 'form-web',
            'PUT' => 'api-rest',
            'PATCH' => 'api-rest',
            'DELETE' => 'api-rest',
        ];

        return $mapping[strtoupper($method)] ?? 'api-rest';
    }

    /**
     * Map auth type for frontend display
     */
    private function mapAuthType($authType)
    {
        $mapping = [
            'oauth' => 'oauth2',
            'api_key' => 'api_key',
            'token' => 'token',
            'none' => 'ninguna',
        ];

        return $mapping[$authType] ?? $authType;
    }

    private function mapAuthLabel($authType)
    {
        $mapping = [
            'oauth' => 'OAuth 2.0',
            'api_key' => 'API Key',
            'token' => 'Token',
            'none' => 'Sin autenticación',
        ];

        return $mapping[$authType] ?? $authType;
    }

    /**
     * Map frontend auth type to backend
     */
    private function mapFrontendAuthType($authType)
    {
        $mapping = [
            'oauth2' => 'oauth',
            'sso' => 'oauth',
            'api_key' => 'api_key',
            'ninguna' => 'none',
        ];

        return $mapping[$authType] ?? 'none';
    }

    /**
     * Map service request status
     */
    private function mapStatus($status)
    {
        $mapping = [
            'pending_review' => 'revision',
            'approved' => 'aprobado',
            'rejected' => 'rechazado',
            'needs_modification' => 'revision',
        ];

        return $mapping[$status] ?? $status;
    }

    /**
     * Map enhanced service status
     */
    private function mapEnhancedStatus($status)
    {
        $mapping = [
            'ready_to_publish' => 'aprobado',
            'published' => 'aprobado',
            'active' => 'aprobado',
        ];

        return $mapping[$status] ?? $status;
    }

    private function mapTypeLabel(string $typeValue): string
    {
        $mapping = [
            'api-rest' => 'API REST',
            'form-web' => 'Formulario web',
            'archivo-batch' => 'Archivo batch',
            'proceso-manual' => 'Proceso manual',
        ];

        return $mapping[$typeValue] ?? $typeValue;
    }

    /**
     * Extract numeric ID from slug
     */
    private function extractIdFromSlug($slug)
    {
        $parts = explode('-', $slug);
        $lastPart = end($parts);
        
        if (is_numeric($lastPart)) {
            return (int) $lastPart;
        }
        
        return null;
    }

    private function resolvePublisherId(Request $request): ?int
    {
        return $request->user()?->id ?? Auth::id() ?? 1; // Default to user ID 1 for testing
    }
}