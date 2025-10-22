<?php

namespace App\Http\Controllers\Publicador;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublisherServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::query()
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->orderByDesc('updated_at')
            ->with([
                'currentVersion',
                'versions' => fn ($query) => $query->orderByDesc('release_date'),
            ])
            ->get();

        return ServiceResource::collection($services);
    }

    public function store(StorePublisherServiceRequest $request): JsonResponse
    {
        Log::debug('publicador.store input', [
            'raw' => $request->getContent(),
            'parsed' => $request->all(),
        ]);

        $data = $request->validated();

        $versionData = $data['version'];
        unset($data['version']);

        $data['usage_count'] = $data['usage_count'] ?? 0;

        $service = Service::create($data);

        $service->versions()->create([
            'version' => $versionData['version'],
            'status' => $versionData['status'] ?? 'available',
            'release_date' => $versionData['release_date'] ?? null,
            'compatibility' => $versionData['compatibility'] ?? null,
            'documentation_url' => $versionData['documentation_url'] ?? null,
            'is_requestable' => $versionData['is_requestable'] ?? true,
            'limit_suggestion' => $versionData['limit_suggestion'] ?? null,
            'notes' => $versionData['notes'] ?? null,
        ]);

        $service->load([
            'currentVersion',
            'versions' => fn ($query) => $query->orderByDesc('release_date'),
        ]);

        return (new ServiceResource($service))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Service $service): ServiceResource
    {
        $service->load([
            'currentVersion',
            'versions' => fn ($query) => $query->orderByDesc('release_date'),
        ]);

        return new ServiceResource($service);
    }

    public function duplicate(Service $service): ServiceResource
    {
        $clone = $service->replicate([
            'slug',
            'status',
            'terms_accepted',
            'approved_at',
        ]);

        $clone->name = $service->name . ' (copia)';
        $clone->slug = Str::slug($clone->name . '-' . Str::random(4));
        $clone->status = 'borrador';
        $clone->terms_accepted = false;
        $clone->approved_at = null;
        $clone->push();

        $service->loadMissing('versions');

        foreach ($service->versions as $version) {
            $clone->versions()->create([
                'version' => $version->version,
                'status' => $version->status,
                'release_date' => $version->release_date,
                'compatibility' => $version->compatibility,
                'documentation_url' => $version->documentation_url,
                'is_requestable' => false,
                'limit_suggestion' => $version->limit_suggestion,
                'notes' => $version->notes,
            ]);
        }

        $clone->load(['currentVersion', 'versions']);

        return new ServiceResource($clone);
    }
}
