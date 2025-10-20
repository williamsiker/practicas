<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'short_description',
        'department',
        'category',
        'usage_count',
        'coverage',
        'url',
        'type',
        'status',
        'auth_type',
        'schedule',
        'monthly_limit',
        'tags',
        'labels',
        'owner',
        'documentation_url',
        'terms_accepted',
        'approved_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'labels' => 'array',
        'terms_accepted' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (Service $service) {
            if (empty($service->slug)) {
                $baseSlug = Str::slug(Str::limit($service->name, 60, ''));
                $slug = $baseSlug;
                $suffix = 2;

                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$baseSlug}-{$suffix}";
                    $suffix++;
                }

                $service->slug = $slug;
            }
        });
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ServiceVersion::class);
    }

    public function currentVersion(): HasOne
    {
        return $this->hasOne(ServiceVersion::class)
            ->where('is_requestable', true)
            ->where('status', 'available')
            ->latest('release_date');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    protected function documentationUrl(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ?: optional($this->currentVersion)->documentation_url
        );
    }
}
