<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Zerethon\Flow\Laravel\Facades\Flow;

class MaskingTest extends TestCase
{
    /** @test */
    public function it_masks_a_sensitive_query_string_value_in_the_captured_request_uri_by_default()
    {
        Route::middleware(['flow.trace'])->get('/flow-mask-test', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->get('/flow-mask-test?token=super-secret-value&page=2');
        $response->assertStatus(200);

        $traceId = $response->headers->get('X-Flow-Trace-Id');
        $traceResponse = $this->get("/_flow/trace/{$traceId}");

        $requestNode = collect($traceResponse->json('flow.nodes'))->firstWhere('type', 'request');

        $this->assertStringNotContainsString('super-secret-value', $requestNode['meta']['uri']);
        $this->assertStringContainsString('token=', $requestNode['meta']['uri']);
        $this->assertStringContainsString('page=2', $requestNode['meta']['uri']);
    }

    /** @test */
    public function it_leaves_the_request_uri_unmasked_when_mask_sensitive_data_is_disabled()
    {
        config(['flow.options.mask_sensitive_data' => false]);

        Route::middleware(['flow.trace'])->get('/flow-mask-disabled-test', function () {
            return response()->json(['ok' => true]);
        });

        $response = $this->get('/flow-mask-disabled-test?token=super-secret-value');
        $traceId = $response->headers->get('X-Flow-Trace-Id');
        $traceResponse = $this->get("/_flow/trace/{$traceId}");

        $requestNode = collect($traceResponse->json('flow.nodes'))->firstWhere('type', 'request');

        $this->assertStringContainsString('super-secret-value', $requestNode['meta']['uri']);
    }

    /** @test */
    public function it_masks_sensitive_keyed_meta_passed_to_a_manual_flow_trace_call()
    {
        Route::middleware(['flow.trace'])->get('/flow-mask-meta-test', function () {
            return Flow::trace('external', 'call-partner-api', function () {
                return response()->json(['ok' => true]);
            }, ['apiKey' => 'sk_live_should_not_leak', 'endpoint' => 'partner']);
        });

        $response = $this->get('/flow-mask-meta-test');
        $traceId = $response->headers->get('X-Flow-Trace-Id');
        $traceResponse = $this->get("/_flow/trace/{$traceId}");

        $externalNode = collect($traceResponse->json('flow.nodes'))->firstWhere('type', 'external');

        $this->assertSame('****', $externalNode['meta']['apiKey']);
        $this->assertSame('partner', $externalNode['meta']['endpoint']);
    }
}
