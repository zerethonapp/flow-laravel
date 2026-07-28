<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Route;

class ExceptionCaptureTest extends TestCase
{
    /** @test */
    public function a_thrown_exception_is_recorded_on_the_trace_instead_of_looking_like_success()
    {
        $historyPath = config('flow.storage_path');
        if (is_file($historyPath)) {
            unlink($historyPath);
        }

        Route::middleware(['flow.trace'])->get('/flow-throws', function () {
            throw new \RuntimeException('boom');
        });

        // Laravel's own exception handler renders this to a real 500 —
        // Testbench doesn't swallow it, it behaves exactly like production.
        $response = $this->get('/flow-throws');
        $response->assertStatus(500);

        $records = json_decode(file_get_contents($historyPath), true);
        $record = end($records);

        $this->assertSame('error', $record['result']['status']);
        $this->assertNotEmpty($record['result']['errors']);

        $requestNode = collect($record['flow']['nodes'])->firstWhere('type', 'request');
        $this->assertSame('RuntimeException', $requestNode['meta']['exception']['type']);
        $this->assertSame('boom', $requestNode['meta']['exception']['message']);
        $this->assertSame(500, $requestNode['meta']['http_status']);
    }

    /** @test */
    public function a_normal_response_still_reports_success_with_no_exception_key()
    {
        $historyPath = config('flow.storage_path');
        if (is_file($historyPath)) {
            unlink($historyPath);
        }

        Route::middleware(['flow.trace'])->get('/flow-ok', function () {
            return response()->json(['ok' => true]);
        });

        $this->get('/flow-ok')->assertStatus(200);

        $records = json_decode(file_get_contents($historyPath), true);
        $record = end($records);

        $this->assertSame('success', $record['result']['status']);
        $requestNode = collect($record['flow']['nodes'])->firstWhere('type', 'request');
        $this->assertArrayNotHasKey('exception', $requestNode['meta']);
    }

    /** @test */
    public function a_404_style_http_exception_is_not_treated_as_a_captured_error()
    {
        // HttpException (404/403/etc.) is in Laravel's default $dontReport
        // list — report() returns before reaching our reportable()
        // callback, so this deliberately does NOT populate `exception`.
        // These are expected control flow, not the kind of error this
        // capture is meant to surface.
        $historyPath = config('flow.storage_path');
        if (is_file($historyPath)) {
            unlink($historyPath);
        }

        Route::middleware(['flow.trace'])->get('/flow-not-found', function () {
            abort(404);
        });

        $this->get('/flow-not-found')->assertStatus(404);

        $records = json_decode(file_get_contents($historyPath), true);
        $record = end($records);

        $requestNode = collect($record['flow']['nodes'])->firstWhere('type', 'request');
        $this->assertSame(404, $requestNode['meta']['http_status']);
        $this->assertArrayNotHasKey('exception', $requestNode['meta']);
    }
}
