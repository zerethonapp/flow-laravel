<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Zerethon\Flow\Laravel\Transport\HttpPushTransport;
use Zerethon\Flow\Laravel\Transport\NullTransport;
use Zerethon\Flow\Laravel\Transport\TraceTransport;

class TraceTransportTest extends TestCase
{
    /** @test */
    public function it_binds_a_null_transport_when_connected_mode_is_not_configured()
    {
        $this->assertInstanceOf(NullTransport::class, $this->app->make(TraceTransport::class));
    }

    /** @test */
    public function null_transport_send_is_a_no_op()
    {
        Http::fake();

        (new NullTransport())->send(['traceId' => 'abc']);

        Http::assertNothingSent();
    }

    /** @test */
    public function http_push_transport_posts_the_record_with_the_expected_auth_headers()
    {
        Http::fake(['flow.example.com/*' => Http::response(['status' => 'stored'], 201)]);

        $transport = new HttpPushTransport('https://flow.example.com', 'proj-123', 'flw_sk_secret', '1.0', 'local');
        $transport->send(['traceId' => 'trace-1']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://flow.example.com/api/v1/traces'
                && $request->hasHeader('Authorization', 'Bearer flw_sk_secret')
                && $request->hasHeader('X-Flow-Project', 'proj-123')
                && $request->hasHeader('X-Flow-Version', '1.0')
                && $request->hasHeader('X-Flow-Environment', 'local')
                && $request['traceId'] === 'trace-1';
        });
    }

    /** @test */
    public function http_push_transport_swallows_connection_failures()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('connection refused');
        });

        $transport = new HttpPushTransport('https://flow.example.com', 'proj-123', 'flw_sk_secret', '1.0', 'local');

        // Must not throw.
        $transport->send(['traceId' => 'trace-1']);
        $this->assertTrue(true);
    }

    /** @test */
    public function http_push_transport_logs_a_warning_on_connection_failure()
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('connection refused');
        });
        Log::spy();

        (new HttpPushTransport('https://flow.example.com', 'proj-123', 'flw_sk_secret', '1.0', 'local'))
            ->send(['traceId' => 'trace-1']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'push failed') && $context['traceId'] === 'trace-1');
    }

    /** @test */
    public function http_push_transport_logs_a_warning_when_flow_api_rejects_the_push(): void
    {
        Http::fake(['flow.example.com/*' => Http::response(['error' => 'invalid project or secret'], 401)]);
        Log::spy();

        (new HttpPushTransport('https://flow.example.com', 'proj-123', 'flw_sk_secret', '1.0', 'local'))
            ->send(['traceId' => 'trace-1']);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message, array $context) => str_contains($message, 'rejected') && $context['traceId'] === 'trace-1' && $context['status'] === 401);
    }
}
