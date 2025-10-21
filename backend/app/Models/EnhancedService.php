<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EnhancedService extends Model
{
    protected $table = 'enhanced_services';

    protected $fillable = [
        'name',
        'description',
        'url',
        'method',
        'status',
        'version',
        'publisher_id',
        'source_request_id',
        'requires_auth',
        'auth_type',
        'auth_config',
        'documentation',
        'parameters',
        'responses',
        'error_codes',
        'validations',
        'metrics_enabled',
        'metrics_config',
        'has_demo',
        'demo_url',
        'base_price',
        'pricing_tiers',
        'max_requests_per_day',
        'max_requests_per_month',
        'features',
        'approved_by',
        'approved_at',
        'approval_notes',
        'rejection_reason',
        'terms_accepted',
        'terms_accepted_at',
        // Additional fields for Phase 3 (publisher configuration)
        'published_at',
        'published_by',
        'operational_config', // For schedules, limits, access control
    ];

    protected $casts = [
        'auth_config' => 'array',
        'parameters' => 'array',
        'responses' => 'array',
        'error_codes' => 'array',
        'validations' => 'array',
        'metrics_config' => 'array',
        'pricing_tiers' => 'array',
        'features' => 'array',
        'operational_config' => 'array',
        'requires_auth' => 'boolean',
        'metrics_enabled' => 'boolean',
        'has_demo' => 'boolean',
        'terms_accepted' => 'boolean',
        'base_price' => 'decimal:2',
        'approved_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    // Relationships
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function sourceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'source_request_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ServiceUsage::class, 'service_id');
    }

    public function plans(): HasMany
    {
        return $this->hasMany(ServicePlan::class, 'service_id');
    }

    // Scopes
    public function scopeReadyToPublish($query)
    {
        return $query->where('status', 'ready_to_publish');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByPublisher($query, $publisherId)
    {
        return $query->where('publisher_id', $publisherId);
    }

    // Helper methods
    public function isReadyToPublish(): bool
    {
        return $this->status === 'ready_to_publish';
    }

    public function isPublished(): bool
    {
        return in_array($this->status, ['published', 'active']);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canBePublished(): bool
    {
        return $this->status === 'ready_to_publish';
    }

    public function canBeConfigured(): bool
    {
        return in_array($this->status, ['ready_to_publish', 'published']);
    }

    // Business logic methods
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
