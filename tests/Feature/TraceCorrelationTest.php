<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\Support\ReadsLocalTraceFile;
use Tests\TestCase;

class TraceCorrelationTest extends TestCase
{
    use ReadsLocalTraceFile;

    /** @test */
    public function it_returns_a_trace_id_header_and_writes_the_matching_record_to_flow_history_json()
    {
        Route::middleware(['flow.trace'])->get('/flow-correlation-test', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->get('/flow-correlation-test');
        $response->assertStatus(200);
        $response->assertHeader('X-Flow-Trace-Id');

        $traceId = $response->headers->get('X-Flow-Trace-Id');
        $this->assertNotEmpty($traceId);

        $record = $this->readTraceRecord($traceId);
        $this->assertNotNull($record);
        $this->assertSame($traceId, $record['traceId']);

        $types = collect($record['flow']['nodes'])->pluck('type');
        $this->assertTrue($types->contains('request'));
        $this->assertTrue($types->contains('controller'));
    }

    /** @test */
    public function it_appends_multiple_captures_to_flow_history_json_newest_last()
    {
        Route::middleware(['flow.trace'])->get('/flow-list-test-one', function () {
            return response()->json(['ok' => true]);
        });
        Route::middleware(['flow.trace'])->get('/flow-list-test-two', function () {
            return response()->json(['ok' => true]);
        });

        $first = $this->get('/flow-list-test-one');
        $second = $this->get('/flow-list-test-two');

        $records = $this->readAllTraceRecords();
        $traceIds = collect($records)->pluck('traceId');

        $this->assertTrue($traceIds->contains($first->headers->get('X-Flow-Trace-Id')));
        $this->assertSame($second->headers->get('X-Flow-Trace-Id'), $traceIds->last());
    }
}
