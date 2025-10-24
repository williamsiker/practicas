<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

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

    protected static function booted(): void
    {
        static::created(function (self $service): void {
            $service->applyManagedEndpoint();
        });

        static::updating(function (self $service): void {
            if ($service->isDirty('name') || $service->isDirty('operational_config')) {
                $service->applyManagedEndpoint(false);
            }
        });
    }

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

    public function applyManagedEndpoint(bool $persist = true): void
    {
        $config = $this->operational_config ?? [];

        if (! is_array($config)) {
            $config = [];
        }

        $baseSegment = Arr::get($config, 'endpoint_base');
        if (! $baseSegment) {
            $baseSegment = 'servicio' . $this->id;
        }
        $baseSegment = Str::slug(trim($baseSegment, '/'));
        if ($baseSegment === '') {
            $baseSegment = 'servicio' . $this->id;
        }

        $slugSegment = Arr::get($config, 'endpoint_slug');
        if (! $slugSegment) {
            $slugSegment = Str::slug($this->name);
        }
        $slugSegment = Str::slug($slugSegment);
        if ($slugSegment === '') {
            $slugSegment = (string) $this->id;
        }

        $uniqueSlug = $slugSegment;
        $candidate = '/' . trim($baseSegment, '/') . '/' . trim($uniqueSlug, '/');
        $suffix = 1;

        while (
            static::where('id', '!=', $this->id)
                ->where('url', $candidate)
                ->exists()
        ) {
            $suffix++;
            $uniqueSlug = $slugSegment . '-' . $suffix;
            $candidate = '/' . trim($baseSegment, '/') . '/' . trim($uniqueSlug, '/');
        }

        $originalUrl = $this->getOriginal('url') ?: $this->url;
        if (! Arr::has($config, 'original_url') && $originalUrl && $originalUrl !== $candidate) {
            Arr::set($config, 'original_url', $originalUrl);
        }

        Arr::set($config, 'endpoint_base', trim($baseSegment, '/'));
        Arr::set($config, 'endpoint_slug', trim($uniqueSlug, '/'));
        Arr::set($config, 'managed_endpoint', $candidate);

        $this->forceFill([
            'url' => $candidate,
            'operational_config' => $config,
        ]);

        if ($persist) {
            $this->saveQuietly();
        }
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
