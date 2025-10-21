<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServicePlan extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'plan_name',
        'monthly_price',
        'requests_per_day',
        'requests_per_month',
        'features_included',
        'is_active',
        'subscribed_at',
        'expires_at',
    ];

    protected $casts = [
        'features_included' => 'array',
        'monthly_price' => 'decimal:2',
        'is_active' => 'boolean',
        'subscribed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(ServiceUsage::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
