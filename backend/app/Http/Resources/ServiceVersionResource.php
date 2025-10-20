<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ServiceVersion */
class ServiceVersionResource extends JsonResource
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
            'version' => $this->version,
            'status' => $this->status,
            'releaseDate' => optional($this->release_date)->toDateString(),
            'compatibility' => $this->compatibility,
            'documentation' => $this->documentation_url,
            'requestable' => $this->is_requestable,
            'limitSuggestion' => $this->limit_suggestion,
            'notes' => $this->notes,
        ];
    }
}
