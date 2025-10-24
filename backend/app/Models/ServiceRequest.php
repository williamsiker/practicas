<?php

namespace App\Models;

use App\Models\EnhancedService;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'url',
        'method',
        'version',
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
        'justification',
        'terms_accepted',
        'terms_accepted_at',
        'status',
        'publisher_id',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        'approved_service_id',
    ];

    protected $casts = [
        'auth_config' => 'array',
        'parameters' => 'array',
        'responses' => 'array',
        'error_codes' => 'array',
        'validations' => 'array',
        'metrics_enabled' => 'boolean',
        'metrics_config' => 'array',
        'has_demo' => 'boolean',
        'pricing_tiers' => 'array',
        'features' => 'array',
        'terms_accepted' => 'boolean',
        'terms_accepted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publisher_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function approvedService(): BelongsTo
    {
        return $this->belongsTo(EnhancedService::class, 'approved_service_id');
    }

    public function scopeByPublisher(Builder $query, int $publisherId): Builder
    {
        return $query->where('publisher_id', $publisherId);
    }

    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where('status', 'pending_review');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', 'rejected');
    }

    public function scopeNeedsModification(Builder $query): Builder
    {
        return $query->where('status', 'needs_modification');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending_review';
    }

    public function getSlugAttribute(): string
    {
        return Str::slug($this->name . '-' . $this->id);
    }
}
