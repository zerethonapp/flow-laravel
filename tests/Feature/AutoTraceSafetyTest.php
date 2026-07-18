<?php

namespace Tests\Feature;

use Zerethon\Flow\Laravel\Instrumentation\TraceCollector;
use Zerethon\Flow\Laravel\Instrumentation\TracingProxyFactory;
use Illuminate\Support\Facades\Route;
use Tests\Support\Domain\Billing\InvoiceService;
use Tests\Support\NoteModel;
use Tests\TestCase;

class AutoTraceSafetyTest extends TestCase
{
    /** @test */
    public function it_never_wraps_eloquent_models_even_when_they_fall_under_trace_namespaces()
    {
        $model = new NoteModel();

        $wrapped = TracingProxyFactory::wrap($model, app(TraceCollector::class));

        $this->assertSame(
            NoteModel::class,
            $wrapped::class,
            'Eloquent model was wrapped in a tracing proxy subclass — this breaks late static binding',
        );
    }

    /** @test */
    public function it_discovers_deeply_nested_ddd_style_classes_without_explicit_per_folder_config()
    {
        if (!is_dir(storage_path('archon-traces'))) {
            mkdir(storage_path('archon-traces'), 0777, true);
        }

        Route::middleware(['flow.trace'])->get('/flow-ddd-test', function () {
            // Tests\Support\Domain\Billing\InvoiceService — three levels
            // deeper than the configured 'Tests\Support' namespace root.
            // No extra config entry was added for the Domain\Billing folder.
            $result = app(InvoiceService::class)->issue();

            return response()->json(['result' => $result]);
        });

        $response = $this->get('/flow-ddd-test');
        $response->assertStatus(200);
        $response->assertJson(['result' => 'issued']);

        $files = glob(storage_path('archon-traces/*.json'));
        $traceFile = end($files);
        $this->assertNotFalse($traceFile, 'No trace file generated');

        $content = json_decode(file_get_contents($traceFile), true);
        $serviceNodes = collect($content['nodes'])->where('type', 'service');

        $this->assertNotEmpty($serviceNodes, 'Nested DDD-style class was not auto-discovered');
        $this->assertStringContainsString('InvoiceService.issue', $serviceNodes->first()['label']);
    }
}
