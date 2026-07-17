<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TraceCorrelationTest extends TestCase
{
    /** @test */
    public function it_returns_a_trace_id_response_header_and_the_trace_is_fetchable_by_that_id()
    {
        Route::middleware(['archon.trace'])->get('/archon-correlation-test', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->get('/archon-correlation-test');
        $response->assertStatus(200);
        $response->assertHeader('X-Archon-Trace-Id');

        $traceId = $response->headers->get('X-Archon-Trace-Id');
        $this->assertNotEmpty($traceId);

        $traceResponse = $this->get("/_archon/trace/{$traceId}");
        $traceResponse->assertStatus(200);
        $traceResponse->assertJsonPath('traceId', $traceId);

        $types = collect($traceResponse->json('flow.nodes'))->pluck('type');
        $this->assertTrue($types->contains('request'));
        $this->assertTrue($types->contains('controller'));
    }

    /** @test */
    public function it_returns_404_for_an_unknown_trace_id()
    {
        $response = $this->get('/_archon/trace/does-not-exist');

        $response->assertStatus(404);
    }
}
