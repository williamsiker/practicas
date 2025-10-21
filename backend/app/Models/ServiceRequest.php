<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
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
        'metrics_config' => 'array',
        'pricing_tiers' => 'array',
        'features' => 'array',
        'requires_auth' => 'boolean',
        'metrics_enabled' => 'boolean',
        'has_demo' => 'boolean',
        'terms_accepted' => 'boolean',
        'base_price' => 'decimal:2',
        'terms_accepted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // Relationships
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

    // Scopes
    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeNeedsModification($query)
    {
        return $query->where('status', 'needs_modification');
    }

    public function scopeByPublisher($query, $publisherId)
    {
        return $query->where('publisher_id', $publisherId);
    }

    // Helper methods
    public function isPending(): bool
    {
        return $this->status === 'pending_review';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function needsModification(): bool
    {
        return $this->status === 'needs_modification';
    }

    public function canBeModified(): bool
    {
        return in_array($this->status, ['pending_review', 'needs_modification']);
    }

    public function hasApprovedService(): bool
    {
        return ! is_null($this->approved_service_id);
    }
}
