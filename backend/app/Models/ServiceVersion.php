<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'version',
        'status',
        'release_date',
        'compatibility',
        'documentation_url',
        'is_requestable',
        'limit_suggestion',
        'notes',
    ];

    protected $casts = [
        'release_date' => 'date',
        'is_requestable' => 'boolean',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }
}
