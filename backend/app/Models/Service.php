<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'description',
        'category',
        'status',
        'base_price',
        'pricing_tiers',
        'max_requests_per_day',
        'max_requests_per_month',
        'features',
        'api_endpoint',
        'documentation_url',
    ];

    protected $casts = [
        'pricing_tiers' => 'array',
        'features' => 'array',
        'base_price' => 'decimal:2',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(ServiceUsage::class);
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ServicePlan::class);
    }

    public function getTotalUsageToday()
    {
        return $this->usages()
            ->whereDate('usage_date', today())
            ->sum('requests_count');
    }

    public function getTotalRevenueThisMonth()
    {
        return $this->usages()
            ->whereMonth('usage_date', now()->month)
            ->whereYear('usage_date', now()->year)
            ->sum('cost');
    }

    public function getActiveSubscribersCount()
    {
        return $this->plans()
            ->where('is_active', true)
            ->distinct('user_id')
            ->count();
    }
}
