<?php

namespace Tests;

use Zerethon\Flow\Laravel\Providers\FlowServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            FlowServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);

        $app['config']->set('flow.enabled', true);
        // Testbench runs the app in the "testing" environment; the package's
        // own default excludes "testing" from tracing, which would silently
        // disable the feature under test. Tests opt back in explicitly.
        $app['config']->set('flow.except_environments', []);
        $app['config']->set('flow.capture_controller', true);
        $app['config']->set('flow.capture_query_sql', false);
        $app['config']->set('flow.trace_directory', storage_path('flow-traces'));
        $app['config']->set('flow.trace_namespaces', [
            'Tests\\Support' => __DIR__ . '/Support',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        // Trace files persist on disk across tests (shared Testbench storage
        // path); glob() order isn't creation-time order, so a stale file from
        // a previous test can be mistaken for the current one. Start clean.
        foreach (glob(storage_path('flow-traces/*.json')) ?: [] as $file) {
            unlink($file);
        }
    }
}
