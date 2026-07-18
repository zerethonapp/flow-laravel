<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\Support\AutoTracedService;
use Tests\Support\GreetingService;
use Tests\Support\GreetingServiceInterface;
use Tests\Support\TraceableAwareService;
use Tests\TestCase;

class AutoInstrumentationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        // Must be bound before FlowServiceProvider::boot() runs so
        // ServiceAutoTraceRegistrar sees it in Container::getBindings().
        $app->bind(GreetingServiceInterface::class, GreetingService::class);
    }

    /** @test */
    public function it_auto_detects_external_http_calls_without_manual_tracing()
    {
        if (!is_dir(storage_path('archon-traces'))) {
            mkdir(storage_path('archon-traces'), 0777, true);
        }

        Http::fake([
            'example.test/*' => Http::response(['ok' => true], 200),
        ]);

        Route::middleware(['flow.trace'])->get('/flow-external-test', function () {
            // No Flow::trace('external', ...) wrapping here on purpose.
            $response = Http::get('https://example.test/ping');

            return response()->json(['status' => $response->status()]);
        });

        $response = $this->get('/flow-external-test');
        $response->assertStatus(200);

        $files = glob(storage_path('archon-traces/*.json'));
        $traceFile = end($files);
        $this->assertNotFalse($traceFile, 'No trace file generated');

        $content = json_decode(file_get_contents($traceFile), true);
        $externalNodes = collect($content['nodes'])->where('type', 'external');

        $this->assertNotEmpty($externalNodes, 'No external node was auto-captured');
        $this->assertStringContainsString('example.test/ping', $externalNodes->first()['label']);
    }

    /** @test */
    public function it_auto_detects_service_method_calls_without_manual_tracing()
    {
        if (!is_dir(storage_path('archon-traces'))) {
            mkdir(storage_path('archon-traces'), 0777, true);
        }

        Route::middleware(['flow.trace'])->get('/flow-service-test', function () {
            // Resolved through the container; the class itself has no
            // Traceable trait and no Flow::trace() call.
            $result = app(AutoTracedService::class)->run();

            return response()->json(['result' => $result]);
        });

        $response = $this->get('/flow-service-test');
        $response->assertStatus(200);
        $response->assertJson(['result' => 'ok']);

        $files = glob(storage_path('archon-traces/*.json'));
        $traceFile = end($files);
        $this->assertNotFalse($traceFile, 'No trace file generated');

        $content = json_decode(file_get_contents($traceFile), true);
        $serviceNodes = collect($content['nodes'])->where('type', 'service');

        $this->assertNotEmpty($serviceNodes, 'No service node was auto-captured');
        $this->assertStringContainsString('AutoTracedService.run', $serviceNodes->first()['label']);
    }

    /** @test */
    public function it_auto_detects_services_resolved_through_an_interface_binding()
    {
        if (!is_dir(storage_path('archon-traces'))) {
            mkdir(storage_path('archon-traces'), 0777, true);
        }

        Route::middleware(['flow.trace'])->get('/flow-interface-test', function () {
            // Type-hinted to the interface, never to GreetingService itself.
            // Container::extend() keyed only by the concrete class name
            // would never fire for this — see ServiceAutoTraceRegistrar.
            $result = app(GreetingServiceInterface::class)->greet();

            return response()->json(['result' => $result]);
        });

        $response = $this->get('/flow-interface-test');
        $response->assertStatus(200);
        $response->assertJson(['result' => 'hello']);

        $content = json_decode(file_get_contents($this->latestTraceFile()), true);
        $serviceNodes = collect($content['nodes'])->where('type', 'service');

        $this->assertNotEmpty($serviceNodes, 'No service node was auto-captured for the interface-bound service');
        $this->assertStringContainsString('GreetingService.greet', $serviceNodes->first()['label']);
    }

    /** @test */
    public function it_does_not_double_wrap_a_service_that_already_self_instruments_via_traceable()
    {
        if (!is_dir(storage_path('archon-traces'))) {
            mkdir(storage_path('archon-traces'), 0777, true);
        }

        Route::middleware(['flow.trace'])->get('/flow-traceable-test', function () {
            // Resolved through the container AND under trace_namespaces, so
            // it's a candidate for the auto-proxy too. It already
            // self-instruments via Traceable — must produce exactly one
            // 'service' node, not two nested ones with the same label.
            $result = app(TraceableAwareService::class)->run();

            return response()->json(['result' => $result]);
        });

        $response = $this->get('/flow-traceable-test');
        $response->assertStatus(200);
        $response->assertJson(['result' => 'ok']);

        $content = json_decode(file_get_contents($this->latestTraceFile()), true);
        $serviceNodes = collect($content['nodes'])
            ->where('type', 'service')
            ->where('label', 'TraceableAwareService.run');

        $this->assertCount(
            1,
            $serviceNodes,
            'Expected exactly one service node; the Traceable class was double-wrapped by the auto-proxy',
        );
    }

    private function latestTraceFile(): string
    {
        $files = glob(storage_path('archon-traces/*.json'));
        $traceFile = end($files);
        $this->assertNotFalse($traceFile, 'No trace file generated');

        return $traceFile;
    }
}
