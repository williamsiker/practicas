<?php

namespace Tests\Feature;

use App\Models\EnhancedService;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class FrontendContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('La extensión pdo_sqlite es necesaria para ejecutar las pruebas de contrato del frontend.');
        }

        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    private function createPublisher(): User
    {
        return User::factory()->create();
    }

    private function seedServiceRequest(User $publisher, array $overrides = []): ServiceRequest
    {
        return ServiceRequest::create(array_merge([
            'name' => 'Solicitud API ' . Str::random(5),
            'description' => 'Servicio para pruebas de integración.',
            'url' => 'https://api.example.com',
            'method' => 'GET',
            'version' => '1.0.0',
            'requires_auth' => true,
            'auth_type' => 'oauth',
            'documentation' => 'Documentación disponible en https://api.example.com/docs',
            'parameters' => [],
            'responses' => [],
            'error_codes' => [],
            'validations' => [],
            'metrics_enabled' => true,
            'metrics_config' => ['schedule' => 'office', 'monthly_limit' => 2000],
            'has_demo' => false,
            'demo_url' => null,
            'base_price' => 0,
            'pricing_tiers' => [],
            'max_requests_per_day' => 1000,
            'max_requests_per_month' => 2000,
            'features' => [],
            'justification' => 'Validar flujo completo desde el publicador.',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'status' => 'pending_review',
            'publisher_id' => $publisher->id,
        ], $overrides));
    }

    private function seedEnhancedService(User $publisher, array $overrides = []): EnhancedService
    {
        return EnhancedService::create(array_merge([
            'name' => 'Servicio publicado ' . Str::random(5),
            'description' => 'Servicio aprobado listo para consumo.',
            'url' => 'https://services.example.com',
            'method' => 'GET',
            'status' => 'ready_to_publish',
            'version' => '1.2.0',
            'publisher_id' => $publisher->id,
            'source_request_id' => null,
            'requires_auth' => true,
            'auth_type' => 'oauth',
            'auth_config' => [],
            'documentation' => 'Manual en https://services.example.com/docs',
            'parameters' => [],
            'responses' => [],
            'error_codes' => [],
            'validations' => [],
            'metrics_enabled' => true,
            'metrics_config' => ['schedule' => 'full', 'monthly_limit' => 5000],
            'has_demo' => false,
            'demo_url' => null,
            'base_price' => 0,
            'pricing_tiers' => [],
            'max_requests_per_day' => 1000,
            'max_requests_per_month' => 5000,
            'features' => [],
            'approved_by' => null,
            'approved_at' => now(),
            'approval_notes' => 'Aprobado automáticamente.',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'operational_config' => [
                'schedule' => 'full',
                'monthly_limit' => 5000,
                'owner' => 'Equipo API',
            ],
        ], $overrides));
    }

    public function test_listado_servicios_publicador_cumple_contrato_frontend(): void
    {
        $publisher = $this->createPublisher();

        $pending = $this->seedServiceRequest($publisher);
        $published = $this->seedEnhancedService($publisher);

        $response = $this->actingAs($publisher)->getJson('/api/publicador/services');

        $response->assertOk();

        $payload = collect($response->json());

        $this->assertTrue($payload->contains(fn ($service) => $service['id'] === $pending->id && $service['status'] === 'revision'));
        $this->assertTrue($payload->contains(fn ($service) => $service['id'] === $published->id && $service['status'] === 'aprobado'));

        $sample = $payload->first();
        $this->assertArrayHasKey('versions', $sample);
        $this->assertIsArray($sample['versions']);
        $this->assertArrayHasKey('version', $sample['versions'][0]);
        $this->assertArrayHasKey('status', $sample['versions'][0]);
    }

    public function test_publicador_puede_crear_solicitud_servicio_via_api(): void
    {
        $publisher = $this->createPublisher();

        $payload = [
            'name' => 'Mesa de partes digital',
            'short_description' => 'Permite enviar trámites digitalmente a la entidad.',
            'url' => 'https://gob.pe/tramites',
            'type' => 'api-rest',
            'status' => 'revision',
            'auth_type' => 'oauth2',
            'schedule' => 'office',
            'monthly_limit' => 3000,
            'terms_accepted' => true,
            'version' => [
                'version' => '2.0.0',
                'status' => 'available',
                'release_date' => now()->toDateString(),
                'compatibility' => 'Pendiente',
                'documentation_url' => 'https://gob.pe/tramites',
                'is_requestable' => true,
            ],
        ];

        $response = $this->actingAs($publisher)->postJson('/api/publicador/services', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Mesa de partes digital')
            ->assertJsonPath('data.status', 'revision')
            ->assertJsonPath('status', 'success')
            ->assertJson(fn (AssertableJson $json) => $json
                ->has('data.versions', 1)
                ->where('data.versions.0.version', '2.0.0')
                ->etc()
            );

        $this->assertDatabaseHas('service_requests', [
            'name' => 'Mesa de partes digital',
            'publisher_id' => $publisher->id,
        ]);
    }

    public function test_endpoint_admin_servicios_pendientes_incluye_campos_esperados(): void
    {
        $publisher = $this->createPublisher();
        $admin = User::factory()->create();

        $request = $this->seedServiceRequest($publisher);

        $response = $this->actingAs($admin)->getJson('/api/admin/services/pending');

        $response->assertOk()
            ->assertJson(fn (AssertableJson $json) => $json
                ->has(1)
                ->first(fn (AssertableJson $item) => $item
                    ->where('id', $request->id)
                    ->where('slug', Str::slug($request->name . '-' . $request->id))
                    ->where('status', 'revision')
                    ->has('versions')
                    ->etc()
                )
            );
    }

    public function test_admin_puede_aprobar_solicitud_servicio_por_slug(): void
    {
        $publisher = $this->createPublisher();
        $admin = User::factory()->create();
        $request = $this->seedServiceRequest($publisher);

        $slug = Str::slug($request->name . '-' . $request->id);

        $response = $this->actingAs($admin)->postJson("/api/admin/services/{$slug}/approve");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $approvedServiceId = EnhancedService::where('name', $request->name)->value('id');

        $this->assertNotNull($approvedServiceId, 'Se esperaba que se creara un servicio aprobado.');

        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'status' => 'approved',
            'approved_service_id' => $approvedServiceId,
        ]);

        $this->assertDatabaseHas('enhanced_services', [
            'id' => $approvedServiceId,
            'name' => $request->name,
            'status' => 'ready_to_publish',
        ]);

        $approvedService = EnhancedService::find($approvedServiceId);
        $this->assertNotNull($approvedService);
        $this->assertMatchesRegularExpression(
            sprintf('#^/servicio%s/#', $approvedService->id),
            $approvedService->url
        );
        $this->assertSame(
            $approvedService->url,
            Arr::get($approvedService->operational_config, 'managed_endpoint')
        );
        $this->assertSame(
            $request->url,
            Arr::get($approvedService->operational_config, 'original_url')
        );
    }

    public function test_admin_puede_rechazar_solicitud_servicio_por_slug(): void
    {
        $publisher = $this->createPublisher();
        $admin = User::factory()->create();
        $request = $this->seedServiceRequest($publisher);

        $slug = Str::slug($request->name . '-' . $request->id);

        $response = $this->actingAs($admin)->postJson("/api/admin/services/{$slug}/reject");

        $response->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('service_requests', [
            'id' => $request->id,
            'status' => 'rejected',
        ]);
    }

    public function test_admin_puede_actualizar_endpoint_administrado_del_servicio(): void
    {
        $publisher = $this->createPublisher();
        $admin = User::factory()->create();
        $request = $this->seedServiceRequest($publisher, ['url' => 'https://example.test/svc']);

        $slug = Str::slug($request->name . '-' . $request->id);
        $this->actingAs($admin)->postJson("/api/admin/services/{$slug}/approve")->assertOk();

        $service = EnhancedService::where('name', $request->name)->firstOrFail();
        $serviceSlug = Str::slug($service->name . '-' . $service->id);

        $payload = [
            'endpoint_base' => 'servicio-especial',
            'endpoint_slug' => 'mesa-digital',
        ];

        $this->actingAs($admin)
            ->patchJson("/api/admin/services/{$serviceSlug}/endpoint", $payload)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.managed_endpoint', '/servicio-especial/mesa-digital');

        $service->refresh();

        $this->assertSame('/servicio-especial/mesa-digital', $service->url);
        $this->assertSame(
            '/servicio-especial/mesa-digital',
            Arr::get($service->operational_config, 'managed_endpoint')
        );
        $this->assertSame(
            $request->url,
            Arr::get($service->operational_config, 'original_url')
        );
    }

    public function test_endpoint_administrado_genera_sufijos_para_evitar_conflictos(): void
    {
        $publisher = $this->createPublisher();
        $admin = User::factory()->create();

        $firstRequest = $this->seedServiceRequest($publisher, ['name' => 'Servicio Uno', 'url' => 'https://example.test/uno']);
        $secondRequest = $this->seedServiceRequest($publisher, ['name' => 'Servicio Dos', 'url' => 'https://example.test/dos']);

        $firstSlug = Str::slug($firstRequest->name . '-' . $firstRequest->id);
        $secondSlug = Str::slug($secondRequest->name . '-' . $secondRequest->id);

        $this->actingAs($admin)->postJson("/api/admin/services/{$firstSlug}/approve")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/services/{$secondSlug}/approve")->assertOk();

        $firstService = EnhancedService::where('name', $firstRequest->name)->firstOrFail();
        $secondService = EnhancedService::where('name', $secondRequest->name)->firstOrFail();

        $sharedPayload = [
            'endpoint_base' => 'servicio-comun',
            'endpoint_slug' => 'canal',
        ];

        $firstServiceSlug = Str::slug($firstService->name . '-' . $firstService->id);
        $secondServiceSlug = Str::slug($secondService->name . '-' . $secondService->id);

        $this->actingAs($admin)
            ->patchJson("/api/admin/services/{$firstServiceSlug}/endpoint", $sharedPayload)
            ->assertOk()
            ->assertJsonPath('data.managed_endpoint', '/servicio-comun/canal');

        $this->actingAs($admin)
            ->patchJson("/api/admin/services/{$secondServiceSlug}/endpoint", $sharedPayload)
            ->assertOk()
            ->assertJsonPath('data.managed_endpoint', '/servicio-comun/canal-2');

        $firstService->refresh();
        $secondService->refresh();

        $this->assertSame('/servicio-comun/canal', $firstService->url);
        $this->assertSame('/servicio-comun/canal-2', $secondService->url);
    }

    public function test_publicador_visualiza_endpoint_dinamico_en_listado(): void
    {
        $publisher = $this->createPublisher();
        $admin = User::factory()->create();
        $request = $this->seedServiceRequest($publisher, [
            'name' => 'Servicio Integral de Atención',
            'url' => 'https://gob.pe/servicio-integral',
        ]);

        $slug = Str::slug($request->name . '-' . $request->id);
        $this->actingAs($admin)->postJson("/api/admin/services/{$slug}/approve")->assertOk();

        $service = EnhancedService::where('name', $request->name)->firstOrFail()->fresh();

        $response = $this->actingAs($publisher)->getJson('/api/publicador/services');
        $response->assertOk();

        $payload = collect($response->json());
        $entry = $payload->firstWhere('id', $service->id);

        $this->assertNotNull($entry, 'Se esperaba que el servicio aprobado aparezca en el listado del publicador.');
        $this->assertSame($service->url, $entry['url']);
        $this->assertMatchesRegularExpression(
            sprintf('#^/servicio%s/#', $service->id),
            $entry['url']
        );
    }

    public function test_endpoint_consumidor_catalogo_retorna_servicio_transformado(): void
    {
        $publisher = $this->createPublisher();
        $service = $this->seedEnhancedService($publisher)->fresh();

        $response = $this->getJson('/api/consumidor/services');

        $response->assertOk();

        $payload = collect($response->json());
        $entry = $payload->firstWhere('id', $service->id);

        $this->assertNotNull($entry, 'Se esperaba encontrar el servicio sembrado en la respuesta del catálogo de consumidor.');
        $this->assertSame(Str::slug($service->name . '-' . $service->id), $entry['slug']);
        $this->assertSame('aprobado', $entry['status']);
        $this->assertSame($service->url, $entry['url']);
        $this->assertMatchesRegularExpression(
            sprintf('#^/servicio%s/#', $service->id),
            $entry['url']
        );
        $this->assertArrayHasKey('versions', $entry);
        $this->assertGreaterThan(0, count($entry['versions']));
    }

    public function test_consumidor_puede_enviar_solicitud_uso_servicio(): void
    {
        $publisher = $this->createPublisher();
        $service = $this->seedEnhancedService($publisher, ['status' => 'published']);

        $slug = Str::slug($service->name . '-' . $service->id);
        $versionId = $service->id * 1000;

        $response = $this->postJson("/api/consumidor/services/{$slug}/versions/{$versionId}/requests", [
            'schedule' => 'office',
            'customStart' => null,
            'customEnd' => null,
            'monthlyLimit' => 1200,
            'notes' => 'Necesitamos acceso para seguimiento.',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.serviceSlug', $slug)
            ->assertJsonPath('data.versionId', (string) $versionId);
    }
}
