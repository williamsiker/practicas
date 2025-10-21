<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnhancedService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class ServiceCatalogController extends Controller
{
    /**
     * Get all available filters for the service catalog.
     */
    public function getAvailableFilters()
    {
        try {
            $services = $this->getCatalogDataset();

            $categories = array_values(array_filter(array_unique(array_map(
                fn ($service) => $service['category'] ?? null,
                $services
            ))));

            $statusOptions = array_map(
                fn ($status) => [
                    'value' => $status,
                    'label' => Str::ucfirst($status),
                ],
                array_values(array_unique(array_map(
                    fn ($service) => $service['status'] ?? 'aprobado',
                    $services
                )))
            );

            $priceRanges = [
                ['label' => 'Gratis', 'min' => 0, 'max' => 0],
                ['label' => '1 - 50', 'min' => 1, 'max' => 50],
                ['label' => '51 - 100', 'min' => 51, 'max' => 100],
                ['label' => '101 - 500', 'min' => 101, 'max' => 500],
                ['label' => '500+', 'min' => 500, 'max' => null],
            ];

            $requestLimits = [
                ['label' => 'Hasta 1,000 / día', 'max_daily' => 1000],
                ['label' => '1,000 - 10,000 / día', 'max_daily' => 10000],
                ['label' => '10,000+ / día', 'max_daily' => null],
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'categories' => $categories,
                    'price_ranges' => $priceRanges,
                    'status_options' => $statusOptions,
                    'request_limits' => $requestLimits,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch filters',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get filtered service catalog with pagination.
     */
    public function getServiceCatalog(Request $request)
    {
        try {
            $services = $this->getCatalogDataset();

            if ($request->filled('category')) {
                $category = Str::lower($request->category);
                $services = array_filter($services, fn ($service) => Str::lower($service['category'] ?? '') === $category);
            }

            if ($request->filled('status')) {
                $status = Str::lower($request->status);
                $services = array_filter($services, fn ($service) => Str::lower($service['status'] ?? '') === $status);
            }

            if ($request->filled('tag')) {
                $tag = $request->tag;
                $services = array_filter($services, fn ($service) => in_array($tag, $service['tags'] ?? []));
            }

            if ($request->filled('min_price')) {
                $minPrice = (float) $request->min_price;
                $services = array_filter($services, fn ($service) => ($service['basePrice'] ?? 0) >= $minPrice);
            }

            if ($request->filled('max_price')) {
                $maxPrice = (float) $request->max_price;
                $services = array_filter($services, fn ($service) => ($service['basePrice'] ?? 0) <= $maxPrice);
            }

            if ($request->filled('min_daily_requests')) {
                $minDaily = (int) $request->min_daily_requests;
                $services = array_filter($services, fn ($service) => ($service['maxRequestsPerDay'] ?? 0) >= $minDaily);
            }

            if ($request->filled('max_daily_requests')) {
                $maxDaily = (int) $request->max_daily_requests;
                $services = array_filter($services, fn ($service) => ($service['maxRequestsPerDay'] ?? 0) <= $maxDaily);
            }

            if ($request->filled('search')) {
                $search = Str::lower($request->search);
                $services = array_filter($services, function ($service) use ($search) {
                    return Str::contains(Str::lower($service['name'] ?? ''), $search)
                        || Str::contains(Str::lower($service['description'] ?? ''), $search);
                });
            }

            $services = array_values($services);

            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = Str::lower($request->get('sort_order', 'asc')) === 'desc' ? 'desc' : 'asc';

            $sortableFields = ['name', 'category', 'status', 'usage', 'basePrice', 'updatedAt'];

            if (in_array($sortBy, $sortableFields, true)) {
                usort($services, function ($a, $b) use ($sortBy, $sortOrder) {
                    $aValue = $a[$sortBy] ?? null;
                    $bValue = $b[$sortBy] ?? null;

                    if (in_array($sortBy, ['basePrice', 'usage'], true)) {
                        $comparison = ($aValue ?? 0) <=> ($bValue ?? 0);
                    } elseif ($sortBy === 'updatedAt') {
                        $comparison = strtotime($aValue ?? '') <=> strtotime($bValue ?? '');
                    } else {
                        $comparison = strcmp(Str::lower($aValue ?? ''), Str::lower($bValue ?? ''));
                    }

                    return $sortOrder === 'asc' ? $comparison : -$comparison;
                });
            }

            $perPage = (int) min(max($request->get('per_page', 12), 1), 50);
            $page = (int) max($request->get('page', 1), 1);
            $offset = ($page - 1) * $perPage;

            $paginatedServices = array_slice($services, $offset, $perPage);

            return response()->json($paginatedServices);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch service catalog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get service details by ID.
     */
    public function getServiceDetails($id)
    {
        try {
            $services = $this->getCatalogDataset();

            $service = collect($services)->first(fn ($svc) => (int) ($svc['id'] ?? 0) === (int) $id);

            if (! $service) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

            return response()->json($service);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch service details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a service request from a consumer.
     */
    public function createServiceRequest(Request $request, $slug, $versionId)
    {
        try {
            $validatedData = $request->validate([
                'schedule' => 'required|string|in:office,full,custom',
                'customStart' => 'nullable|string',
                'customEnd' => 'nullable|string',
                'monthlyLimit' => 'required|integer|min:1',
                'notes' => 'nullable|string',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Service request created successfully',
                'data' => [
                    'id' => rand(1000, 9999),
                    'serviceSlug' => $slug,
                    'versionId' => $versionId,
                    'schedule' => $validatedData['schedule'],
                    'monthlyLimit' => $validatedData['monthlyLimit'],
                    'notes' => $validatedData['notes'] ?? null,
                    'status' => 'pending',
                    'createdAt' => now()->toISOString(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create service request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Load services from static JSON (Mock data).
     */
    public function loadMockServices()
    {
        try {
            return response()->json([
                'status' => 'success',
                'message' => 'Mock services are ready to use from static dataset',
                'data' => [
                    'services_count' => count($this->loadServicesFromJson()),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load mock services',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function getCatalogDataset(): array
    {
        $services = EnhancedService::whereIn('status', ['ready_to_publish', 'published', 'active'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (EnhancedService $service) => $this->transformEnhancedService($service))
            ->all();

        if (! empty($services)) {
            return $services;
        }

        return $this->loadServicesFromJson();
    }

    private function loadServicesFromJson(): array
    {
        $filePath = base_path('database/data/mock-services.json');

        if (! file_exists($filePath)) {
            return $this->fallbackServices();
        }

        $jsonContent = file_get_contents($filePath);
        $decoded = json_decode($jsonContent, true);

        if (! is_array($decoded)) {
            return $this->fallbackServices();
        }

        return array_map(fn ($service) => $this->normalizeServicePayload($service), $decoded);
    }

    private function transformEnhancedService(EnhancedService $service): array
    {
        $metrics = $service->metrics_config ?? [];
        $operational = $service->operational_config ?? [];

        $schedule = Arr::get($operational, 'schedule')
            ?? Arr::get($metrics, 'schedule', 'office');

        $monthlyLimit = Arr::get($operational, 'monthly_limit')
            ?? Arr::get($metrics, 'monthly_limit')
            ?? $service->max_requests_per_month;

        return [
            'id' => $service->id,
            'slug' => Str::slug($service->name . '-' . $service->id),
            'name' => $service->name,
            'description' => $service->description,
            'department' => Arr::get($operational, 'department', 'Sin departamento'),
            'category' => Arr::get($operational, 'category', 'Servicios digitales'),
            'status' => $this->mapStatus($service->status),
            'usage' => Arr::get($operational, 'usage', 0),
            'coverage' => Arr::get($operational, 'coverage', 'No especificada'),
            'url' => $service->url,
            'type' => $this->mapMethodToType($service->method),
            'typeLabel' => $this->mapTypeLabel($this->mapMethodToType($service->method)),
            'authType' => $this->mapAuthType($service->auth_type),
            'authTypeLabel' => $this->mapAuthLabel($service->auth_type),
            'schedule' => $schedule,
            'monthlyLimit' => $monthlyLimit,
            'tags' => Arr::get($operational, 'tags', []),
            'labels' => Arr::get($operational, 'labels', []),
            'owner' => Arr::get($operational, 'owner', 'No asignado'),
            'documentationUrl' => $service->documentation,
            'termsAccepted' => (bool) $service->terms_accepted,
            'currentVersion' => $service->version,
            'updatedAt' => optional($service->updated_at ?? $service->created_at)->toISOString(),
            'basePrice' => (float) $service->base_price,
            'maxRequestsPerDay' => $service->max_requests_per_day,
            'maxRequestsPerMonth' => $service->max_requests_per_month,
            'versions' => [[
                'id' => $service->id * 1000,
                'version' => $service->version,
                'status' => 'available',
                'releaseDate' => optional($service->approved_at ?? $service->created_at)->toDateString(),
                'compatibility' => Arr::get($operational, 'compatibility', 'N/A'),
                'documentationUrl' => $service->documentation,
                'requestable' => true,
                'limitSuggestion' => $monthlyLimit,
                'notes' => $service->documentation ? 'Documentación disponible.' : 'Sin notas adicionales.',
            ]],
        ];
    }

    private function normalizeServicePayload(array $service): array
    {
        $slug = $service['slug'] ?? Str::slug(($service['name'] ?? 'servicio') . '-' . ($service['id'] ?? Str::random(6)));
        $updatedAt = $service['updatedAt'] ?? now()->toISOString();

        $versions = [];
        foreach ($service['versions'] ?? [] as $index => $version) {
            $versions[] = [
                'id' => $version['id'] ?? ($index + 1),
                'version' => $version['version'] ?? '1.0.0',
                'status' => $version['status'] ?? 'available',
                'releaseDate' => $version['releaseDate'] ?? now()->toDateString(),
                'compatibility' => $version['compatibility'] ?? 'N/A',
                'documentationUrl' => $version['documentationUrl'] ?? $version['documentation'] ?? null,
                'requestable' => $version['requestable'] ?? $version['is_requestable'] ?? true,
                'limitSuggestion' => $version['limitSuggestion'] ?? null,
                'notes' => $version['notes'] ?? null,
            ];
        }

        return array_merge($service, [
            'slug' => $slug,
            'updatedAt' => $updatedAt,
            'versions' => $versions,
            'tags' => $service['tags'] ?? [],
            'labels' => $service['labels'] ?? [],
            'status' => $service['status'] ?? 'aprobado',
            'usage' => $service['usage'] ?? 0,
            'basePrice' => $service['basePrice'] ?? 0,
            'maxRequestsPerDay' => $service['maxRequestsPerDay'] ?? 1000,
            'maxRequestsPerMonth' => $service['maxRequestsPerMonth'] ?? 30000,
        ]);
    }

    private function fallbackServices(): array
    {
        return [];
    }

    private function mapStatus(string $status): string
    {
        $mapping = [
            'ready_to_publish' => 'aprobado',
            'published' => 'aprobado',
            'active' => 'aprobado',
            'pending' => 'revision',
            'draft' => 'borrador',
            'maintenance' => 'mantenimiento',
        ];

        return $mapping[$status] ?? $status;
    }

    private function mapMethodToType(string $method): string
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

    private function mapAuthType(string $authType): string
    {
        $mapping = [
            'oauth' => 'oauth2',
            'api_key' => 'api_key',
            'token' => 'token',
            'none' => 'ninguna',
        ];

        return $mapping[$authType] ?? $authType;
    }

    private function mapAuthLabel(string $authType): string
    {
        $mapping = [
            'oauth' => 'OAuth 2.0',
            'api_key' => 'API Key',
            'token' => 'Token',
            'none' => 'Sin autenticación',
        ];

        return $mapping[$authType] ?? $authType;
    }
}
