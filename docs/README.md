# 🚀 flow-laravel

> Laravel instrumentation adapter for **Flow**  
> Capture real Laravel execution traces and feed them into the Flow engine.

---

## 🧭 What is `flow-laravel`?

`flow-laravel` is the first real instrumentation adapter for Flow.

Its job is to move Flow from:

- mock execution flows
- synthetic timing
- demo-only traces

to:

- real Laravel request tracing
- real nodes and edges
- real execution timing
- real trace output for CLI analysis

---

## 🎯 Goal

Provide the smallest practical Laravel package that can:

- capture one real HTTP request
- trace controller execution
- capture database activity
- support manual service tracing
- emit Flow-compatible trace JSON

---

## 🏗 Package Responsibilities

This package is responsible for:

- Laravel runtime instrumentation
- lifecycle hooks
- node / edge creation from real execution
- trace assembly
- trace writing

This package is **not** responsible for:

- bottleneck scoring
- confidence analysis
- insight generation
- CLI formatting

Those belong to:

- `flow-core`
- `flow-engine`
- `flow-cli`

---

## 📦 Planned Features (Phase 3 v1)

### ✅ Included
- request start / end capture
- controller node capture
- DB query listener
- manual service tracing helper
- optional external call tracing helper
- trace JSON output
- CLI compatibility

### ❌ Not yet included
- distributed tracing
- queue tracing
- production-grade sampling
- UI/dashboard
- automatic tracing of every service magically

---

## 🧩 How It Works

```text
Laravel Request
→ Middleware starts trace
→ Controller is resolved
→ Service blocks are traced manually
→ DB queries are captured
→ External calls are optionally wrapped
→ Trace is assembled
→ Trace JSON is written
→ flow-cli analyzes the trace
```

---

## 📁 Suggested Structure

```text
src/
├── Providers/
├── Facades/
├── Contracts/
├── Instrumentation/
├── Middleware/
├── Support/
└── Helpers/
```

---

## ⚙️ Installation (planned)

```bash
composer require zerethonapp/flow-laravel
```

Publish config:

```bash
php artisan vendor:publish --tag=flow-config
```

---

## 🔧 Basic Setup (planned)

Register middleware or enable package config so each request can be traced.

Example manual service instrumentation:

```php
use Zerethon\Flow\Laravel\Facades\Flow;

$result = Flow::trace('service', 'UserService.findUser', function () {
    return app(UserService::class)->findUser();
});
```

---

## 🗂 Example Output

A captured trace should produce a structure similar to:

```json
{
  "traceId": "trace-123",
  "totalTime": 24,
  "nodes": [
    { "id": "request", "type": "request", "duration": 24 },
    { "id": "controller", "type": "controller", "duration": 18 },
    { "id": "findUser", "type": "service", "duration": 10 },
    { "id": "users.select", "type": "database", "duration": 4 }
  ],
  "edges": [
    { "from": "request", "to": "controller", "type": "call" },
    { "from": "controller", "to": "findUser", "type": "call" },
    { "from": "findUser", "to": "users.select", "type": "call" }
  ]
}
```

---

## 🚀 Phase 3 Success Criteria

Phase 3 v1 is successful when:

- `flow-laravel` installs into a Laravel app
- one real request generates a trace
- controller and DB activity are captured
- manual service tracing works
- trace JSON is compatible with `flow-cli`
- Flow is no longer mock-only

---

## 🧠 Design Principles

- practical over perfect
- clean boundaries over framework leakage
- real data over synthetic demos
- progressive instrumentation over over-engineering

---

## 🔥 Why This Matters

This package is the breakthrough step for Flow.

It proves that Flow can operate on:

> **real application execution**, not just simulated flow graphs

That changes the project from:

- concept validation

to:

- real execution intelligence

---

## 🗺 Roadmap

### Phase 3 v1
- real Laravel request tracing
- controller capture
- DB capture
- manual service tracing
- JSON trace output

### Future
- HTTP client tracing
- queue/job tracing
- Eloquent model event insights
- package polish
- production-safe configuration

---

## 💬 Final Statement

`flow-laravel` is not just a package.

It is the first bridge between:

> **real Laravel execution**  
> and  
> **Flow intelligence**

---

## 🔥 Tagline

> Capture real execution. Understand real flow.
