<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Console;

use Zerethon\Flow\Laravel\Discovery\RouteDiscovery;
use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Application Discovery: lists every route, its Core contract fields
 * (validation, authentication, risk, payload) and Laravel-specific
 * framework metadata — purely by reading Laravel's own route table and
 * reflecting controller signatures. Never sends a request and never
 * resolves a controller/FormRequest through the container. Independent of
 * traffic, unlike everything else this package currently captures. Output
 * follows the Application Discovery Contract v1.1 (`flow-docs/ADAPTER_PROTOCOL.md`).
 */
final class RoutesCommand extends Command
{
    private const CONTRACT_VERSION = '1.1.0';

    protected $signature = 'flow:routes {--json : Output raw JSON instead of a table} {--push : Also push the snapshot to Flow API (requires Connected mode to be configured)}';

    protected $description = "Discover the application's routes, validation, authentication, and risk without sending any requests";

    public function handle(RouteDiscovery $discovery, Router $router): int
    {
        $routes = $discovery->discover($router);

        if ($this->option('json')) {
            $this->line((string) json_encode($this->envelope($routes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->displayTable($routes);
            $this->components->info(sprintf('%d route(s) discovered — no requests were sent.', count($routes)));
        }

        if (! $this->option('push')) {
            return self::SUCCESS;
        }

        return $this->push($routes);
    }

    /** @param array<int, array<string, mixed>> $routes */
    private function displayTable(array $routes): void
    {
        $this->table(
            ['Method', 'URI', 'Risk', 'Auth', 'Controller', 'Validated By'],
            array_map(static function (array $route): array {
                $laravel = $route['framework']['laravel'];
                $controller = $laravel['action']['controller'];

                return [
                    implode('|', $route['methods']),
                    $route['uri'],
                    $route['risk'],
                    $route['authentication']['required'] ? implode(',', $route['authentication']['strategies']) ?: 'required' : '—',
                    $controller !== null ? class_basename($controller).'@'.$laravel['action']['method'] : '(closure)',
                    $laravel['formRequest'] !== null ? class_basename($laravel['formRequest']) : '',
                ];
            }, $routes),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     * @return array<string, mixed>
     */
    private function envelope(array $routes): array
    {
        return [
            'contractVersion' => self::CONTRACT_VERSION,
            'adapterMetadata' => $this->adapterMetadata(),
            'routes' => $routes,
        ];
    }

    /** @return array<string, string> */
    private function adapterMetadata(): array
    {
        return [
            'adapterVersion' => InstalledVersions::isInstalled('zerethonapp/flow-laravel')
                ? (InstalledVersions::getPrettyVersion('zerethonapp/flow-laravel') ?? 'unknown')
                : 'unknown',
            'frameworkVersion' => app()->version(),
            'discoveryMode' => 'reflection',
            'framework' => 'laravel',
            'language' => 'php',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $routes
     *
     * Same auth shape as HttpPushTransport/InstallCommand (Bearer secret +
     * X-Flow-Project), but deliberately a plain, synchronous, on-demand call
     * here rather than going through TraceTransport — this runs once from a
     * developer/deploy-pipeline invocation, not on every request, so there's
     * no response-latency concern to defer around.
     */
    private function push(array $routes): int
    {
        $server = (string) config('flow.connected.server', '');
        $projectId = (string) config('flow.connected.project_id', '');
        $secretKey = (string) config('flow.connected.secret_key', '');

        if ($server === '' || $projectId === '' || $secretKey === '') {
            $this->components->error('--push requires FLOW_SERVER, FLOW_PROJECT_ID, and FLOW_SECRET_KEY to all be set (Connected mode).');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(10)
                ->withToken($secretKey)
                ->withHeaders(['X-Flow-Project' => $projectId])
                ->post(rtrim($server, '/').'/api/v1/discovery', $this->envelope($routes));
        } catch (Throwable $e) {
            $this->components->error("Could not reach {$server}: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->components->error("Flow API rejected the discovery push (HTTP {$response->status()}): {$response->body()}");

            return self::FAILURE;
        }

        $this->components->info("Pushed {$this->routeWord(count($routes))} to {$server}.");

        return self::SUCCESS;
    }

    private function routeWord(int $count): string
    {
        return $count === 1 ? '1 route' : "{$count} routes";
    }
}
