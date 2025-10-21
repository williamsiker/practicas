<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceUsage extends Model
{
    protected $fillable = [
        'user_id',
        'service_id',
        'service_plan_id',
        'requests_count',
        'cost',
        'metadata',
        'usage_date',
        'ip_address',
        'user_agent',
        'status',
        'response_time_ms',
    ];

    protected $casts = [
        'metadata' => 'array',
        'cost' => 'decimal:2',
        'usage_date' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function servicePlan(): BelongsTo
    {
        return $this->belongsTo(ServicePlan::class);
    }
}
