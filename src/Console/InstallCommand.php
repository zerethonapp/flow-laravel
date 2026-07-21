<?php

declare(strict_types=1);

namespace Zerethon\Flow\Laravel\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

final class InstallCommand extends Command
{
    protected $signature = 'flow:install';

    protected $description = 'Validate FLOW_SERVER/FLOW_PROJECT_ID/FLOW_SECRET_KEY against Flow API';

    public function handle(): int
    {
        $server = (string) config('flow.connected.server', '');
        $projectId = (string) config('flow.connected.project_id', '');
        $secretKey = (string) config('flow.connected.secret_key', '');

        if ($server === '' || $projectId === '' || $secretKey === '') {
            $this->components->error('FLOW_SERVER, FLOW_PROJECT_ID, and FLOW_SECRET_KEY must all be set in .env.');

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(5)
                ->withToken($secretKey)
                ->withHeaders(['X-Flow-Project' => $projectId])
                ->post(rtrim($server, '/').'/api/v1/ping');
        } catch (Throwable $e) {
            $this->components->error("Could not reach {$server}: {$e->getMessage()}");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->components->error("Flow API rejected the credentials (HTTP {$response->status()}).");

            return self::FAILURE;
        }

        $this->components->info('Connected mode is configured correctly — traces will be pushed to '.$server.'.');

        return self::SUCCESS;
    }
}
