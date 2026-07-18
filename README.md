# flow-laravel

Laravel instrumentation adapter for Flow Phase 3 & 4 (real runtime traces).

## Why Flow?

Most developers:

- ❌ Optimize database first (without proof)
- ❌ Guess bottlenecks based on intuition  
- ❌ Rely on generic monitoring tools

**Flow shows the REAL bottleneck instantly:**

- ✅ Identifies actual slow operations
- ✅ Prevents wasted optimization effort
- ✅ Provides clear, explainable insights

### Real Example

Your request takes **100ms**. Where's the bottleneck?

**Without Flow:**
- Maybe add database index? 🤔
- Cache everything? 🤔
- Optimize queries? 🤔

**With Flow:**
```
╔═══════════════════════════════════════════════════════════════════════════╗
║                           FLOW EXECUTION ANALYSIS                         ║
╚═══════════════════════════════════════════════════════════════════════════╝

  Top Bottleneck:    UserService.fetch
  Duration:          90ms
  Impact:            90%
  Classification:    clear bottleneck

  Database:          2ms (2%)
```

**Conclusion:** Database is NOT the problem. Focus on service logic instead.

---

## What this package does

- **Zero-config**: Automatically captures HTTP requests after install
- Captures one real Laravel HTTP request as a Flow trace
- Produces real nodes and edges from runtime execution
- Stores trace records in `.archon/flow-history.json` (CLI-compatible format)
- Supports manual service/external instrumentation for v1 practicality
- Captures database query timings through Laravel's DB listener

## Scope (v1)

Included:

- request lifecycle node
- controller scoped node
- database query nodes
- manual service traces (`Flow::trace(...)`)
- manual external traces (`Flow::external(...)`)
- JSON storage compatible with `flow-cli scan/analyze`

Not included yet:

- distributed tracing
- queue/job tracing
- automatic service discovery
- UI/dashboard

## Install

```bash
composer require zerethonapp/flow-laravel
```

**That's it!** Flow will now automatically trace HTTP requests in non-production environments.

Hit any route:

```bash
curl http://your-app.test/any-route
```

Check the trace:

```bash
cat .archon/flow-history.json
```

Or browse individual traces:

```bash
ls storage/archon-traces/
```

## Zero-Config Experience

By default, Flow:
- ✅ Automatically registers middleware globally
- ✅ Captures all HTTP requests (web + api)
- ✅ Captures controller execution
- ✅ Captures database queries
- ✅ Writes traces to `.archon/flow-history.json`
- ✅ Only runs in non-production environments

**No code changes required.**

## Configuration (Optional)

Publish config if you want to customize behavior:

```bash
php artisan vendor:publish --tag=archonflow-config
```

The published config file at `config/archonflow.php` provides fine-grained control:

### Enable/Disable

```php
// Explicitly enable or disable (null = auto-detect based on environment)
'enabled' => env('ARCHONFLOW_ENABLED'),

// Environments where Flow should NOT run
'except_environments' => ['production', 'testing'],

// URI patterns to exclude from tracing
'except' => [
    'telescope*',
    'horizon*',
],

// Sample rate (0.0 - 1.0): trace only a percentage of requests
'sample_rate' => env('ARCHONFLOW_SAMPLE_RATE', 1.0),
```

### Sources

Control which data sources are active:

```php
'sources' => [
    'request' => true,      // Request lifecycle
    'controller' => true,   // Controller execution
    'database' => true,     // Database queries
    'external' => true,     // External HTTP calls
],
```

### Source Options

Configure behavior per source:

```php
'options' => [
    'database' => [
        'capture_sql' => false,      // Include SQL text in trace
        'capture_bindings' => false, // Include query bindings
    ],
],
```

### Storage

```php
'storage_path' => base_path('.archon/flow-history.json'),
'trace_directory' => storage_path('archon-traces'),
'max_records' => 1000,
```

## Enable request capture middleware

Add middleware alias `flow.trace` to routes you want to capture.

Example:

```php
Route::middleware(['flow.trace'])->group(function () {
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});
```

## Manual service and external tracing

Use the Facade:

```php
use Zerethon\Flow\Laravel\Facades\Flow;

$order = Flow::traceService('OrderService.findOrder', function () use ($id) {
    return $this->orderService->findOrder($id);
});

$payload = Flow::traceExternal('BillingApi.charge', function () use ($order) {
    return Http::post('https://billing.example.com/charge', ['order_id' => $order->id])->json();
});
```

Or use the global helper:

```php
flow()->traceService('UserService.findUser', fn () => $service->findUser($id));
```

Generic trace method:

```php
Flow::trace('service', 'UserService.findUser', fn () => $service->findUser($id));
Flow::trace('external', 'PaymentApi.charge', fn () => $api->charge($amount));
```

## Assisted Tracing

Flow provides multiple ways to make tracing easier and less verbose:

### Using the Traceable Trait

Add the trait to your service classes:

```php
use Zerethon\Flow\Laravel\Support\Traceable;

class UserService
{
    use Traceable;

    public function findUser(int $id): User
    {
        return $this->traceService('UserService.findUser', function () use ($id) {
            // Business logic here
            return User::find($id);
        });
    }

    public function syncWithExternalApi(): void
    {
        $this->traceExternal('ExternalUserApi.sync', function () {
            // External API call
            Http::post('https://api.example.com/sync');
        });
    }
}
```

### Using PHP Attributes (Future)

```php
use Zerethon\Flow\Laravel\Support\Trace;

class OrderService
{
    #[Trace('service')]
    public function processOrder(int $orderId): void
    {
        // Automatically traced as "OrderService.processOrder"
    }

    #[Trace('external', 'PaymentAPI.charge')]
    public function chargePayment(float $amount): void
    {
        // Automatically traced with custom label
    }
}
```

**Note:** Attribute-based tracing requires additional AOP/interceptor setup and is not yet fully implemented.

## Output format

Traces are appended to:

- `.archon/flow-history.json` (default)

Record shape matches Flow core storage:

- `traceId`
- `timestamp`
- `flow` (`schema_version`, `trace_id`, `nodes`, `edges`, `meta`)
- `result` (`status`, `totalTime`, `nodeCount`, `executedNodes`, optional `errors`)

## Analyze with existing CLI

From the Laravel app root (where `.archon/flow-history.json` exists):

```bash
flow scan
flow analyze
flow status
```

Raw JSON mode:

```bash
flow scan --json
```

## Demo scenario (real request trace)

Use middleware + manual service/external blocks:

```php
// routes/web.php
Route::middleware(['flow.trace'])->get('/flow-demo', function () {
    $user = \Zerethon\Flow\Laravel\Helpers\Flow::trace('service', 'UserService.findUser', function () {
        return DB::table('users')->where('id', 1)->first();
    });

    $remote = \Zerethon\Flow\Laravel\Helpers\Flow::external('BillingApi.status', function () {
        return Http::get('https://httpbin.org/status/200')->status();
    });

    return response()->json(['user' => $user?->id, 'remote_status' => $remote]);
});
```

Then call the route once and run:

```bash
flow scan
```

## Config

`config/archonflow.php`

- `enabled`
- `storage_path`
- `max_records`
- `capture_controller`
- `capture_query_sql`
