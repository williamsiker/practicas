<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard KPIs and metrics
     */
    public function getKPIs()
    {
        try {
            // Get total services from our new services table
            $totalServices = DB::table('services')->count();

            // Get total users as a proxy for offices/organizations
            $totalOffices = DB::table('users')->count();

            // Get total service usages today (from our new service_usages table)
            $requestsToday = DB::table('service_usages')
                ->whereDate('created_at', Carbon::today())
                ->sum('requests_count');

            // Get total service usages this month
            $requestsThisMonth = DB::table('service_usages')
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->sum('requests_count');

            // Get successful requests today (status 'success')
            $successfulRequestsToday = DB::table('service_usages')
                ->whereDate('created_at', Carbon::today())
                ->where('status', 'success')
                ->sum('requests_count');

            // Calculate success rate
            $successRate = $requestsToday > 0 ? round(($successfulRequestsToday / $requestsToday) * 100, 2) : 100;

            // Get error requests today
            $errorRequestsToday = DB::table('service_usages')
                ->whereDate('created_at', Carbon::today())
                ->whereIn('status', ['error', 'timeout'])
                ->sum('requests_count');

            // Get top services by usage today
            $topServices = DB::table('service_usages')
                ->select('services.name', DB::raw('SUM(service_usages.requests_count) as requests'))
                ->join('services', 'service_usages.service_id', '=', 'services.id')
                ->whereDate('service_usages.created_at', Carbon::today())
                ->groupBy('services.id', 'services.name')
                ->orderBy('requests', 'desc')
                ->limit(5)
                ->get();

            // Get active service plans
            $activePlans = DB::table('service_plans')->count();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'overview' => [
                        'total_services' => $totalServices,
                        'total_offices' => $totalOffices,
                        'requests_today' => $requestsToday ?: 0,
                        'requests_this_month' => $requestsThisMonth ?: 0,
                        'success_rate' => $successRate,
                        'error_requests_today' => $errorRequestsToday ?: 0,
                        'active_plans' => $activePlans,
                    ],
                    'top_services' => $topServices,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch KPIs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get usage analytics over time
     */
    public function getUsageAnalytics(Request $request)
    {
        try {
            $days = $request->get('days', 7); // Default to 7 days

            // Get daily usage for the past N days from service_usages
            $dailyUsage = DB::table('service_usages')
                ->select(
                    DB::raw('DATE(usage_date) as date'),
                    DB::raw('SUM(requests_count) as total_requests'),
                    DB::raw('SUM(CASE WHEN status = "success" THEN requests_count ELSE 0 END) as successful_requests'),
                    DB::raw('SUM(CASE WHEN status != "success" THEN requests_count ELSE 0 END) as failed_requests')
                )
                ->where('usage_date', '>=', Carbon::now()->subDays($days))
                ->groupBy(DB::raw('DATE(usage_date)'))
                ->orderBy('date')
                ->get();

            // Get hourly usage for today (sample data since we don't have hourly breakdown)
            $hourlyUsage = collect(range(0, 23))->map(function ($hour) {
                return [
                    'hour' => $hour,
                    'requests' => rand(10, 100),
                ];
            });

            // Get service usage distribution
            $serviceUsage = DB::table('service_usages')
                ->select('services.name as service_name', DB::raw('SUM(service_usages.requests_count) as requests'))
                ->join('services', 'service_usages.service_id', '=', 'services.id')
                ->where('service_usages.usage_date', '>=', Carbon::now()->subDays($days))
                ->groupBy('services.id', 'services.name')
                ->orderBy('requests', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'daily_usage' => $dailyUsage,
                    'hourly_usage' => $hourlyUsage,
                    'service_usage' => $serviceUsage,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get service performance metrics
     */
    public function getServicePerformance()
    {
        try {
            $servicePerformance = DB::table('services')
                ->leftJoin('service_usages', 'services.id', '=', 'service_usages.service_id')
                ->select(
                    'services.name as service_name',
                    'services.description',
                    DB::raw('COALESCE(SUM(service_usages.requests_count), 0) as total_requests'),
                    DB::raw('COALESCE(SUM(CASE WHEN service_usages.status = "success" THEN service_usages.requests_count ELSE 0 END), 0) as successful_requests'),
                    DB::raw('CASE WHEN SUM(service_usages.requests_count) > 0 THEN ROUND((SUM(CASE WHEN service_usages.status = "success" THEN service_usages.requests_count ELSE 0 END) / SUM(service_usages.requests_count)) * 100, 2) ELSE 100 END as success_rate'),
                    DB::raw('COALESCE(SUM(CASE WHEN DATE(service_usages.usage_date) = CURDATE() THEN service_usages.requests_count ELSE 0 END), 0) as today_requests')
                )
                ->groupBy('services.id', 'services.name', 'services.description')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $servicePerformance,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch service performance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get office/organization activity (using users as offices proxy)
     */
    public function getOfficeActivity()
    {
        try {
            $officeActivity = DB::table('users')
                ->leftJoin('service_usages', 'users.id', '=', 'service_usages.user_id')
                ->select(
                    'users.name as office_name',
                    'users.email as description',
                    DB::raw('COALESCE(SUM(service_usages.requests_count), 0) as total_requests'),
                    DB::raw('COALESCE(SUM(CASE WHEN DATE(service_usages.usage_date) = CURDATE() THEN service_usages.requests_count ELSE 0 END), 0) as today_requests'),
                    DB::raw('COALESCE(SUM(CASE WHEN service_usages.usage_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN service_usages.requests_count ELSE 0 END), 0) as week_requests')
                )
                ->groupBy('users.id', 'users.name', 'users.email')
                ->orderBy('total_requests', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $officeActivity,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch office activity',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recent service usage logs with details
     */
    public function getRecentLogs(Request $request)
    {
        try {
            $limit = $request->get('limit', 50);

            $recentLogs = DB::table('service_usages')
                ->leftJoin('services', 'service_usages.service_id', '=', 'services.id')
                ->leftJoin('users', 'service_usages.user_id', '=', 'users.id')
                ->select(
                    'service_usages.*',
                    'services.name as service_name',
                    'users.name as user_name',
                    'users.email as user_email'
                )
                ->orderBy('service_usages.created_at', 'desc')
                ->limit($limit)
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $recentLogs,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch recent logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
