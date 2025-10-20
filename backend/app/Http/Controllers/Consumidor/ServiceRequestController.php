<?php

namespace App\Http\Controllers\Consumidor;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreConsumerServiceRequest;
use App\Http\Resources\ServiceRequestResource;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\ServiceVersion;
use Illuminate\Http\JsonResponse;

class ServiceRequestController extends Controller
{
    public function store(
        StoreConsumerServiceRequest $request,
        Service $service,
        ServiceVersion $version
    ): JsonResponse {
        if ($version->service_id !== $service->id) {
            abort(404, 'La version no pertenece a este servicio.');
        }

        if (!$version->is_requestable) {
            abort(422, 'La version seleccionada no admite nuevas solicitudes.');
        }

        $data = $request->validated();

        if ($data['schedule'] !== 'custom') {
            $data['custom_start'] = null;
            $data['custom_end'] = null;
        }

        $serviceRequest = ServiceRequest::create([
            'service_id' => $service->id,
            'service_version_id' => $version->id,
            'consumer_name' => $data['consumer_name'] ?? null,
            'consumer_email' => $data['consumer_email'] ?? null,
            'schedule' => $data['schedule'],
            'custom_start' => $data['custom_start'] ?? null,
            'custom_end' => $data['custom_end'] ?? null,
            'monthly_limit' => $data['monthly_limit'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return (new ServiceRequestResource($serviceRequest))
            ->response()
            ->setStatusCode(201);
    }
}
