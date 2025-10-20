<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceApprovalController extends Controller
{
    public function pending(Request $request)
    {
        $services = Service::query()
            ->whereIn('status', ['revision'])
            ->orderByDesc('updated_at')
            ->with(['currentVersion', 'versions' => fn ($query) => $query->orderByDesc('release_date')])
            ->get();

        return ServiceResource::collection($services);
    }

    public function approve(Service $service): JsonResponse
    {
        if (!in_array($service->status, ['revision', 'borrador'])) {
            return response()->json([
                'message' => 'Solo se pueden aprobar servicios en revision o borrador.',
            ], 422);
        }

        $service->forceFill([
            'status' => 'aprobado',
            'approved_at' => now(),
        ])->save();

        return (new ServiceResource($service->fresh([
            'currentVersion',
            'versions' => fn ($query) => $query->orderByDesc('release_date'),
        ])))
            ->response()
            ->setStatusCode(200);
    }

    public function reject(Service $service): JsonResponse
    {
        if (!in_array($service->status, ['revision'])) {
            return response()->json([
                'message' => 'Solo se pueden rechazar servicios en revision.',
            ], 422);
        }

        $service->forceFill([
            'status' => 'rechazado',
            'approved_at' => null,
        ])->save();

        return response()->json([
            'message' => 'Servicio rechazado correctamente.',
            'service' => new ServiceResource($service->fresh([
                'currentVersion',
                'versions' => fn ($query) => $query->orderByDesc('release_date'),
            ])),
        ]);
    }
}
