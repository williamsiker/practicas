<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'service_version_id',
        'consumer_name',
        'consumer_email',
        'schedule',
        'custom_start',
        'custom_end',
        'monthly_limit',
        'notes',
        'status',
    ];

    protected $casts = [
        'custom_start' => 'datetime:H:i',
        'custom_end' => 'datetime:H:i',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ServiceVersion::class, 'service_version_id');
    }
}
