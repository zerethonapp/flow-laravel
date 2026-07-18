<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TraceCorrelationTest extends TestCase
{
    /** @test */
    public function it_returns_a_trace_id_response_header_and_the_trace_is_fetchable_by_that_id()
    {
        Route::middleware(['flow.trace'])->get('/flow-correlation-test', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->get('/flow-correlation-test');
        $response->assertStatus(200);
        $response->assertHeader('X-Flow-Trace-Id');

        $traceId = $response->headers->get('X-Flow-Trace-Id');
        $this->assertNotEmpty($traceId);

        $traceResponse = $this->get("/_flow/trace/{$traceId}");
        $traceResponse->assertStatus(200);
        $traceResponse->assertJsonPath('traceId', $traceId);

        $types = collect($traceResponse->json('flow.nodes'))->pluck('type');
        $this->assertTrue($types->contains('request'));
        $this->assertTrue($types->contains('controller'));
    }

    /** @test */
    public function it_returns_404_for_an_unknown_trace_id()
    {
        $response = $this->get('/_flow/trace/does-not-exist');

        $response->assertStatus(404);
    }

    /** @test */
    public function it_lists_recent_traces_with_request_metadata_newest_first()
    {
        Route::middleware(['flow.trace'])->get('/flow-list-test-one', function () {
            return response()->json(['ok' => true]);
        });
        Route::middleware(['flow.trace'])->get('/flow-list-test-two', function () {
            return response()->json(['ok' => true]);
        });

        $first = $this->get('/flow-list-test-one');
        $second = $this->get('/flow-list-test-two');

        $response = $this->get('/_flow/traces');
        $response->assertStatus(200);

        $traceIds = collect($response->json('traces'))->pluck('traceId');
        $this->assertEquals(
            $second->headers->get('X-Flow-Trace-Id'),
            $traceIds->first(),
            'expected the most recently captured trace first',
        );
        $this->assertTrue($traceIds->contains($first->headers->get('X-Flow-Trace-Id')));

        $newest = collect($response->json('traces'))->first();
        $this->assertSame('/flow-list-test-two', $newest['uri']);
        $this->assertSame('success', $newest['status']);
    }
}
