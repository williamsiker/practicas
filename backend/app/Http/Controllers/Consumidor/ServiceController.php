<?php

namespace App\Http\Controllers\Consumidor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::query()
            ->when($request->filled('tag'), function ($query) use ($request) {
                $tag = $request->string('tag')->toString();
                $query->whereJsonContains('tags', $tag);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $value = $request->string('search')->toString();
                $query->where(function ($q) use ($value) {
                    $q->where('name', 'like', "%{$value}%")
                        ->orWhere('short_description', 'like', "%{$value}%")
                        ->orWhere('department', 'like', "%{$value}%");
                });
            })
            ->where('status', 'aprobado')
            ->orderByDesc('updated_at')
            ->with([
                'currentVersion',
                'versions' => fn ($query) => $query
                    ->where('is_requestable', true)
                    ->whereIn('status', ['available', 'maintenance'])
                    ->orderByDesc('release_date'),
            ])
            ->get();

        return ServiceResource::collection($services);
    }

    public function show(Service $service): ServiceResource
    {
        $service->load([
            'currentVersion',
            'versions' => fn ($query) => $query
                ->whereIn('status', ['available', 'maintenance'])
                ->orderByDesc('release_date'),
        ]);

        return new ServiceResource($service);
    }
}
