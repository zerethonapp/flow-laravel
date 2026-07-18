<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\Support\AutoTracedService;
use Tests\TestCase;

class ServiceCacheTest extends TestCase
{
    private string $cachePath;
    private string $emptyNamespaceDir;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $this->emptyNamespaceDir = sys_get_temp_dir() . '/archon-empty-namespace-' . uniqid();
        mkdir($this->emptyNamespaceDir, 0777, true);

        $this->cachePath = sys_get_temp_dir() . '/archon-services-cache-' . uniqid() . '.php';
        file_put_contents(
            $this->cachePath,
            '<?php return ' . var_export([AutoTracedService::class], true) . ';' . PHP_EOL,
        );

        // A live scan of this directory would find nothing — proves the
        // cache manifest is what's actually consulted, not a live walk.
        $app['config']->set('flow.trace_namespaces', [
            'Tests\\Support' => $this->emptyNamespaceDir,
        ]);
        $app['config']->set('flow.services_cache_path', $this->cachePath);
    }

    protected function tearDown(): void
    {
        if (isset($this->cachePath) && is_file($this->cachePath)) {
            unlink($this->cachePath);
        }

        if (isset($this->emptyNamespaceDir) && is_dir($this->emptyNamespaceDir)) {
            rmdir($this->emptyNamespaceDir);
        }

        parent::tearDown();
    }

    /** @test */
    public function it_uses_the_cached_manifest_instead_of_scanning_the_filesystem_live()
    {
        if (!is_dir(storage_path('flow-traces'))) {
            mkdir(storage_path('flow-traces'), 0777, true);
        }

        Route::middleware(['flow.trace'])->get('/flow-cache-test', function () {
            $result = app(AutoTracedService::class)->run();

            return response()->json(['result' => $result]);
        });

        $response = $this->get('/flow-cache-test');
        $response->assertStatus(200);

        $files = glob(storage_path('flow-traces/*.json'));
        $traceFile = end($files);
        $this->assertNotFalse($traceFile, 'No trace file generated');

        $content = json_decode(file_get_contents($traceFile), true);
        $serviceNodes = collect($content['nodes'])->where('type', 'service');

        $this->assertNotEmpty(
            $serviceNodes,
            'Cached manifest was not honored — service was not auto-traced even though it is listed in the cache file',
        );
    }

    /** @test */
    public function flow_cache_services_command_writes_a_valid_manifest()
    {
        // Point at a real, scannable directory for this one so the command
        // has something genuine to discover.
        config(['flow.trace_namespaces' => [
            'Tests\\Support' => __DIR__ . '/../Support',
        ]]);

        $writtenPath = sys_get_temp_dir() . '/archon-cache-command-' . uniqid() . '.php';
        config(['flow.services_cache_path' => $writtenPath]);

        try {
            $this->artisan('flow:cache-services')->assertExitCode(0);

            $this->assertFileExists($writtenPath);

            /** @var mixed $classes */
            $classes = require $writtenPath;

            $this->assertIsArray($classes);
            $this->assertContains(AutoTracedService::class, $classes);
        } finally {
            if (is_file($writtenPath)) {
                unlink($writtenPath);
            }
        }
    }
}
