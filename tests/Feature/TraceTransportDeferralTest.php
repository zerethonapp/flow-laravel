<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Zerethon\Flow\Laravel\Middleware\CaptureFlowTrace;
use Zerethon\Flow\Laravel\Transport\TraceTransport;

class TraceTransportDeferralTest extends TestCase
{
    /** @test */
    public function the_middleware_returns_a_response_before_the_transport_is_invoked()
    {
        $recorder = new class implements TraceTransport {
            public bool $called = false;

            /** @param array<string, mixed> $flowRecord */
            public function send(array $flowRecord): void
            {
                $this->called = true;
            }
        };

        $this->app->instance(TraceTransport::class, $recorder);

        $request = Request::create('/flow-transport-direct-test', 'GET');
        $middleware = $this->app->make(CaptureFlowTrace::class);

        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        // handle() has already returned a response, but the push is only
        // registered to run on the app's terminating callback — proving the
        // response isn't held up waiting for the transport.
        $this->assertFalse(
            $recorder->called,
            'Transport::send() must not run before handle() returns its response.'
        );

        // The real HTTP kernel calls this after the response bytes are sent
        // to the client (e.g. via fastcgi_finish_request under PHP-FPM) —
        // simulate that same lifecycle point directly.
        $this->app->terminate();

        $this->assertTrue($recorder->called, 'Transport::send() must still run once the app terminates.');
        unset($response);
    }

    /** @test */
    public function it_still_delivers_the_trace_record_to_the_transport_by_the_end_of_the_request_lifecycle()
    {
        $recorder = new class implements TraceTransport {
            /** @var array<int, array<string, mixed>> */
            public array $received = [];

            /** @param array<string, mixed> $flowRecord */
            public function send(array $flowRecord): void
            {
                $this->received[] = $flowRecord;
            }
        };

        $this->app->instance(TraceTransport::class, $recorder);

        Route::middleware(['flow.trace'])->get('/flow-transport-test-2', function () {
            return response()->json(['ok' => true]);
        });

        // Testbench's HTTP test helpers call $kernel->terminate() after every
        // request (same as a real Laravel app's public/index.php), which is
        // exactly what fires the deferred dispatch — so this proves delivery
        // still happens, not just that it's deferred.
        $response = $this->get('/flow-transport-test-2');

        $response->assertStatus(200);
        $this->assertCount(1, $recorder->received);
        $this->assertSame(
            $response->headers->get('X-Flow-Trace-Id'),
            $recorder->received[0]['traceId'] ?? null
        );
    }
}
