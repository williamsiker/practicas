<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Service */
class ServiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'department' => $this->department,
            'category' => $this->category,
            'status' => $this->status,
            'type' => $this->type,
            'authType' => $this->auth_type,
            'schedule' => $this->schedule,
            'monthlyLimit' => $this->monthly_limit,
            'metrics' => $this->monthly_limit
                ? ['monthlyCalls' => $this->monthly_limit]
                : null,
            'usage' => $this->usage_count,
            'coverage' => $this->coverage,
            'owner' => $this->owner,
            'description' => $this->short_description,
            'documentationUrl' => $this->documentation_url,
            'url' => $this->url,
            'tags' => $this->tags ?? [],
            'labels' => $this->labels ?? [],
            'currentVersion' => optional($this->currentVersion)->version,
            'termsAccepted' => (bool) $this->terms_accepted,
            'approvedAt' => optional($this->approved_at)?->toIso8601String(),
            'updatedAt' => optional($this->updated_at)?->toIso8601String(),
            'versions' => ServiceVersionResource::collection(
                $this->whenLoaded('versions', fn () => $this->versions->sortByDesc('release_date')->values())
            ),
        ];
    }
}
