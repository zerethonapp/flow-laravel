<?php

namespace Tests\Feature;

use Tests\Support\DemoNotesController;
use Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Zerethon\Flow\Laravel\Discovery\RouteDiscovery;

class RouteDiscoveryTest extends TestCase
{
    private function discover(): array
    {
        return $this->app->make(RouteDiscovery::class)->discover($this->app->make('router'));
    }

    /** @test */
    public function core_fields_are_present_and_framework_independent_for_a_plain_route()
    {
        Route::get('/notes', [DemoNotesController::class, 'index'])->name('notes.index');

        $route = collect($this->discover())->firstWhere('uri', 'notes');

        $this->assertSame(['GET'], $route['methods']);
        $this->assertSame([], $route['parameters']);
        $this->assertNull($route['validation']);
        $this->assertNull($route['payload']);
        $this->assertSame(['required' => false, 'strategies' => []], $route['authentication']);
        $this->assertSame('low', $route['risk']);

        // Laravel-specific detail lives only under framework.laravel, never in Core.
        $this->assertSame('notes.index', $route['framework']['laravel']['routeName']);
        $this->assertSame(DemoNotesController::class, $route['framework']['laravel']['action']['controller']);
    }

    /** @test */
    public function risk_is_derived_from_method_alone()
    {
        Route::get('/risk/get', [DemoNotesController::class, 'index'])->name('risk.get');
        Route::post('/risk/post', [DemoNotesController::class, 'index'])->name('risk.post');
        Route::put('/risk/put', [DemoNotesController::class, 'index'])->name('risk.put');
        Route::delete('/risk/delete', [DemoNotesController::class, 'index'])->name('risk.delete');

        $byName = collect($this->discover())->keyBy(fn (array $r) => $r['framework']['laravel']['routeName']);

        $this->assertSame('low', $byName['risk.get']['risk']);
        $this->assertSame('medium', $byName['risk.post']['risk']);
        $this->assertSame('high', $byName['risk.put']['risk']);
        $this->assertSame('critical', $byName['risk.delete']['risk']);
    }

    /** @test */
    public function it_normalizes_validation_and_generates_a_payload_for_a_safe_form_request()
    {
        Route::post('/notes', [DemoNotesController::class, 'store'])->name('notes.store');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.store');

        $this->assertNotNull($route['validation']);
        $field = $route['validation']['fields'][0];
        $this->assertSame('title', $field['name']);
        $this->assertSame('string', $field['type']);
        $this->assertTrue($field['required']);

        $this->assertSame(['title' => 'string', 'body' => 'string'], $route['payload']);

        // Raw Laravel detail stays available under framework metadata.
        $this->assertSame(\Tests\Support\DemoStoreRequest::class, $route['framework']['laravel']['formRequest']);
        $this->assertSame(
            ['required', 'string', 'max:255'],
            $route['framework']['laravel']['rules']['title']
        );
    }

    /** @test */
    public function it_degrades_validation_and_payload_to_null_while_still_reporting_the_form_request_class()
    {
        Route::put('/notes/{note}', [DemoNotesController::class, 'update'])->name('notes.update');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.update');

        $this->assertSame(['note'], $route['parameters']);
        $this->assertNull($route['validation'], 'rules() reading request state must degrade to null, not throw.');
        $this->assertNull($route['payload']);
        $this->assertSame(\Tests\Support\UnsafeRulesRequest::class, $route['framework']['laravel']['formRequest']);
        $this->assertNull($route['framework']['laravel']['rules']);
    }

    /** @test */
    public function it_reports_closure_routes_with_a_null_action_instead_of_failing()
    {
        Route::get('/notes/ping', fn () => response()->json(['ok' => true]))->name('notes.ping');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.ping');

        $this->assertNull($route['framework']['laravel']['action']['controller']);
        $this->assertNull($route['validation']);
        $this->assertSame([], $route['framework']['laravel']['modelBinding']);
    }

    /** @test */
    public function it_detects_bearer_authentication_from_sanctum_middleware()
    {
        Route::get('/notes/secure', [DemoNotesController::class, 'index'])
            ->middleware(['auth:sanctum'])
            ->name('notes.secure');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.secure');

        $this->assertSame(['required' => true, 'strategies' => ['bearer']], $route['authentication']);
    }

    /** @test */
    public function it_detects_session_authentication_from_the_plain_auth_middleware()
    {
        Route::get('/notes/session', [DemoNotesController::class, 'index'])
            ->middleware(['auth'])
            ->name('notes.session');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.session');

        $this->assertSame(['required' => true, 'strategies' => ['session']], $route['authentication']);
    }

    /** @test */
    public function guest_middleware_marks_authentication_as_not_required()
    {
        Route::get('/notes/guest-only', [DemoNotesController::class, 'index'])
            ->middleware(['guest'])
            ->name('notes.guest');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.guest');

        $this->assertFalse($route['authentication']['required']);
    }

    /** @test */
    public function it_parses_policy_ability_names_from_can_middleware()
    {
        Route::put('/notes/{note}/policy-test', [DemoNotesController::class, 'index'])
            ->middleware(['can:update,note'])
            ->name('notes.policy-test');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.policy-test');

        $this->assertSame(['update,note'], $route['framework']['laravel']['policies']);
    }

    /** @test */
    public function it_detects_route_model_binding_from_a_type_hinted_eloquent_model_parameter()
    {
        Route::get('/notes/{note}', [DemoNotesController::class, 'show'])->name('notes.show');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.show');

        $this->assertSame(
            [['parameter' => 'note', 'model' => \Tests\Support\NoteModel::class, 'routeParam' => 'note']],
            $route['framework']['laravel']['modelBinding']
        );
    }

    /** @test */
    public function it_detects_relationships_with_a_declared_return_type_on_the_bound_model()
    {
        Route::get('/posts/{post}', [DemoNotesController::class, 'showPost'])->name('posts.show');

        $route = collect($this->discover())->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'posts.show');

        $this->assertSame(
            [['model' => \Tests\Support\DemoPostModel::class, 'name' => 'comments', 'type' => 'HasMany']],
            $route['framework']['laravel']['relationships']
        );
    }

    /** @test */
    public function the_artisan_command_lists_discovered_routes_as_a_versioned_envelope_without_sending_requests()
    {
        Route::post('/notes', [DemoNotesController::class, 'store'])->name('notes.store');

        $this->artisan('flow:routes --json')
            ->assertExitCode(0);
    }

    /** @test */
    public function push_fails_fast_when_connected_mode_is_not_configured()
    {
        config(['flow.connected.server' => '', 'flow.connected.project_id' => '', 'flow.connected.secret_key' => '']);

        $this->artisan('flow:routes --push')->assertExitCode(1);
    }

    /** @test */
    public function push_posts_the_versioned_envelope_to_flow_api_with_the_connected_mode_credentials()
    {
        config([
            'flow.connected.server' => 'https://flow.example.com',
            'flow.connected.project_id' => 'proj-123',
            'flow.connected.secret_key' => 'flw_sk_secret',
        ]);

        Http::fake(['flow.example.com/*' => Http::response(['status' => 'stored'], 201)]);

        Route::get('/notes', [DemoNotesController::class, 'index'])->name('notes.index');

        $this->artisan('flow:routes --push')->assertExitCode(0);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://flow.example.com/api/v1/discovery'
                && $request->hasHeader('Authorization', 'Bearer flw_sk_secret')
                && $request->hasHeader('X-Flow-Project', 'proj-123')
                && $request['contractVersion'] === '1.1.0'
                && $request['adapterMetadata']['discoveryMode'] === 'reflection'
                && $request['adapterMetadata']['framework'] === 'laravel'
                && collect($request['routes'])->first(fn (array $r) => $r['framework']['laravel']['routeName'] === 'notes.index') !== null;
        });
    }

    /** @test */
    public function push_fails_when_flow_api_rejects_the_payload()
    {
        config([
            'flow.connected.server' => 'https://flow.example.com',
            'flow.connected.project_id' => 'proj-123',
            'flow.connected.secret_key' => 'wrong-secret',
        ]);

        Http::fake(['flow.example.com/*' => Http::response(['error' => 'invalid project or secret'], 401)]);

        $this->artisan('flow:routes --push')->assertExitCode(1);
    }
}
