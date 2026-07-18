<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Zerethon\Flow\Laravel\Facades\Flow;

class FlowTraceTest extends TestCase
{
    /** @test */
    public function it_generates_real_trace()
    {
        // Ensure trace path exists
        if (!is_dir(storage_path('flow-traces'))) {
            mkdir(storage_path('flow-traces'), 0777, true);
        }

        // Define test route dynamically
        Route::middleware(['flow.trace'])->get('/flow-test', function () {
            return Flow::trace('service', 'TestService.run', function () {
                DB::select('SELECT 1');
                return response()->json(['ok' => true]);
            });
        });

        // Hit route
        $response = $this->get('/flow-test');

        $response->assertStatus(200);

        // Check trace file exists
        $files = glob(storage_path('flow-traces/*.json'));

        $this->assertNotEmpty($files, 'No trace file generated');

        $traceFile = end($files);

        $content = json_decode(file_get_contents($traceFile), true);

        // Basic structure assertions
        $this->assertArrayHasKey('traceId', $content);
        $this->assertArrayHasKey('nodes', $content);
        $this->assertArrayHasKey('edges', $content);

        $types = collect($content['nodes'])->pluck('type');

        $this->assertTrue($types->contains('request'));
        $this->assertTrue($types->contains('controller'));
        $this->assertTrue($types->contains('service'));
        $this->assertTrue($types->contains('database'));
    }
}
