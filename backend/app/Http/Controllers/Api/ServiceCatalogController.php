<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ServiceCatalogController extends Controller
{
    /**
     * Get all available filters for the service catalog
     */
    public function getAvailableFilters()
    {
        try {
            // Load services from JSON file
            $services = $this->loadServicesFromJson();

            // Get unique categories
            $categories = array_values(array_unique(array_column($services, 'category')));

            // Get price ranges
            $priceRanges = [
                ['label' => 'Free', 'min' => 0, 'max' => 0],
                ['label' => '1 - 50', 'min' => 1, 'max' => 50],
                ['label' => '51 - 100', 'min' => 51, 'max' => 100],
                ['label' => '101 - 500', 'min' => 101, 'max' => 500],
                ['label' => '500+', 'min' => 500, 'max' => null],
            ];

            // Service status options
            $statusOptions = [
                ['value' => 'active', 'label' => 'Active'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
                ['value' => 'inactive', 'label' => 'Inactive'],
            ];

            // Request limit filters
            $requestLimits = [
                ['label' => 'Up to 1,000/day', 'max_daily' => 1000],
                ['label' => '1,000 - 10,000/day', 'max_daily' => 10000],
                ['label' => '10,000+/day', 'max_daily' => null],
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
     * Get filtered service catalog with pagination
     */
    public function getServiceCatalog(Request $request)
    {
        try {
            // Load all services from JSON file
            $allServices = $this->loadServicesFromJson();
            $services = $allServices;

            // Apply filters
            if ($request->has('category') && ! empty($request->category)) {
                $services = array_filter($services, function ($service) use ($request) {
                    return $service['category'] === $request->category;
                });
            }

            if ($request->has('status') && ! empty($request->status)) {
                $services = array_filter($services, function ($service) use ($request) {
                    return $service['status'] === $request->status;
                });
            }

            // Price range filter
            if ($request->has('min_price')) {
                $services = array_filter($services, function ($service) use ($request) {
                    return $service['base_price'] >= $request->min_price;
                });
            }
            if ($request->has('max_price') && $request->max_price !== null) {
                $services = array_filter($services, function ($service) use ($request) {
                    return $service['base_price'] <= $request->max_price;
                });
            }

            // Request limit filter
            if ($request->has('min_daily_requests')) {
                $services = array_filter($services, function ($service) use ($request) {
                    return $service['max_requests_per_day'] >= $request->min_daily_requests;
                });
            }
            if ($request->has('max_daily_requests') && $request->max_daily_requests !== null) {
                $services = array_filter($services, function ($service) use ($request) {
                    return $service['max_requests_per_day'] <= $request->max_daily_requests;
                });
            }

            // Search by name or description
            if ($request->has('search') && ! empty($request->search)) {
                $search = strtolower($request->search);
                $services = array_filter($services, function ($service) use ($search) {
                    return strpos(strtolower($service['name']), $search) !== false ||
                           strpos(strtolower($service['description']), $search) !== false;
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'name');
            $sortOrder = $request->get('sort_order', 'asc');

            if (in_array($sortBy, ['name', 'category', 'base_price', 'created_at', 'status'])) {
                usort($services, function ($a, $b) use ($sortBy, $sortOrder) {
                    $aVal = $a[$sortBy];
                    $bVal = $b[$sortBy];

                    if ($sortBy === 'base_price') {
                        $comparison = $aVal <=> $bVal;
                    } else {
                        $comparison = strcasecmp($aVal, $bVal);
                    }

                    return $sortOrder === 'asc' ? $comparison : -$comparison;
                });
            }

            // Reset array keys
            $services = array_values($services);
            $total = count($services);

            // Pagination
            $perPage = min($request->get('per_page', 12), 50); // Max 50 per page
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;

            $paginatedServices = array_slice($services, $offset, $perPage);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'services' => $paginatedServices,
                    'pagination' => [
                        'current_page' => (int) $page,
                        'per_page' => (int) $perPage,
                        'total' => $total,
                        'last_page' => (int) ceil($total / $perPage),
                        'has_next_page' => $page < ceil($total / $perPage),
                        'has_prev_page' => $page > 1,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch service catalog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get service details by ID
     */
    public function getServiceDetails($id)
    {
        try {
            // Load services from JSON file
            $services = $this->loadServicesFromJson();

            // Find service by ID
            $service = null;
            foreach ($services as $s) {
                if ($s['id'] == $id) {
                    $service = $s;
                    break;
                }
            }

            if (! $service) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Service not found',
                ], 404);
            }

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
     * Load services from static JSON (Mock data)
     */
    public function loadMockServices()
    {
        try {
            return response()->json([
                'status' => 'success',
                'message' => 'Mock services are ready to use from static JSON file',
                'data' => [
                    'services_count' => 6,
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

    /**
     * Load mock services from JSON file
     */
    private function loadServicesFromJson()
    {
        $filePath = storage_path('mock-services.json');

        if (! file_exists($filePath)) {
            return [];
        }

        $jsonContent = file_get_contents($filePath);

        return json_decode($jsonContent, true) ?: [];
    }
}
