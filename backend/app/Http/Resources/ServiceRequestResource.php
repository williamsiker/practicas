<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\ServiceRequest */
class ServiceRequestResource extends JsonResource
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
            'serviceId' => $this->service_id,
            'serviceVersionId' => $this->service_version_id,
            'consumerName' => $this->consumer_name,
            'consumerEmail' => $this->consumer_email,
            'schedule' => $this->schedule,
            'customStart' => optional($this->custom_start)->format('H:i'),
            'customEnd' => optional($this->custom_end)->format('H:i'),
            'monthlyLimit' => $this->monthly_limit,
            'notes' => $this->notes,
            'status' => $this->status,
            'createdAt' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
