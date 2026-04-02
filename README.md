# archon-laravel

Laravel instrumentation adapter for ArchonFlow Phase 3 (real runtime traces).

## What this package does

- Captures one real Laravel HTTP request as an ArchonFlow trace
- Produces real nodes and edges from runtime execution
- Stores trace records in `.archon/flow-history.json` (CLI-compatible format)
- Supports manual service/external instrumentation for v1 practicality
- Captures database query timings through Laravel's DB listener

## Scope (v1)

Included:

- request lifecycle node
- controller scoped node
- database query nodes
- manual service traces (`Archon::trace(...)`)
- manual external traces (`Archon::external(...)`)
- JSON storage compatible with `archon-cli scan/analyze`

Not included yet:

- distributed tracing
- queue/job tracing
- automatic service discovery
- UI/dashboard

## Install

```bash
composer require archonflow/laravel
```

Publish config:

```bash
php artisan vendor:publish --tag=archonflow-config
```

## Enable request capture middleware

Add middleware alias `archon.trace` to routes you want to capture.

Example:

```php
Route::middleware(['archon.trace'])->group(function () {
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});
```

## Manual service and external tracing

Use the static helper:

```php
use ArchonFlow\Laravel\Helpers\Archon;

$order = Archon::trace('OrderService.findOrder', function () use ($id) {
    return $this->orderService->findOrder($id);
});

$payload = Archon::external('BillingApi.charge', function () use ($order) {
    return Http::post('https://billing.example.com/charge', ['order_id' => $order->id])->json();
});
```

Or use the global helper:

```php
archon()->traceService('UserService.findUser', fn () => $service->findUser($id));
```

## Output format

Traces are appended to:

- `.archon/flow-history.json` (default)

Record shape matches Archon core storage:

- `traceId`
- `timestamp`
- `flow` (`schema_version`, `trace_id`, `nodes`, `edges`, `meta`)
- `result` (`status`, `totalTime`, `nodeCount`, `executedNodes`, optional `errors`)

## Analyze with existing CLI

From the Laravel app root (where `.archon/flow-history.json` exists):

```bash
archonflow scan
archonflow analyze
archonflow status
```

Raw JSON mode:

```bash
archonflow scan --json
```

## Demo scenario (real request trace)

Use middleware + manual service/external blocks:

```php
// routes/web.php
Route::middleware(['archon.trace'])->get('/archon-demo', function () {
    $user = \ArchonFlow\Laravel\Helpers\Archon::trace('UserService.findUser', function () {
        return DB::table('users')->where('id', 1)->first();
    });

    $remote = \ArchonFlow\Laravel\Helpers\Archon::external('BillingApi.status', function () {
        return Http::get('https://httpbin.org/status/200')->status();
    });

    return response()->json(['user' => $user?->id, 'remote_status' => $remote]);
});
```

Then call the route once and run:

```bash
archonflow scan
```

## Config

`config/archonflow.php`

- `enabled`
- `storage_path`
- `max_records`
- `capture_controller`
- `capture_query_sql`
