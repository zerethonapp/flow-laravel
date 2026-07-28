# Changelog

All notable changes to `flow-laravel` are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.2.0] — 2026-07-28

### Added

- Application Discovery: a reflection-only route/controller/FormRequest
  scanner (`Zerethon\Flow\Laravel\Discovery\RouteDiscovery`) and the
  `php artisan flow:routes` command (`--json` for machine output,
  `--push` to send the snapshot to Flow API). Reads `Route::getRoutes()`
  and reflects controller signatures — never sends a request, never
  resolves a controller or FormRequest through the container. Output
  follows the [Application Discovery Contract v1.1](https://github.com/zerethonapp/flow-docs/blob/main/ADAPTER_PROTOCOL.md):
  a framework-independent Core layer (methods, uri, parameters,
  validation, authentication, payload, risk) plus an optional
  `framework.laravel.*` layer for everything Laravel-specific (route
  name, controller/action, middleware, prefix, domain, FormRequest class
  and raw rules, model binding, relationships, policies).
- `ValidationNormalizer` — parses a bound FormRequest's raw `rules()`
  output into typed field descriptors (name/type/required/nullable/format).
- `PayloadExampleGenerator` — builds an example request payload from
  normalized validation fields. A template only, never submitted
  automatically.
- Authentication detection (`auth`/`auth:sanctum`/`auth:api`/`guest`/
  `auth.basic` middleware → normalized `required`/`strategies`), policy
  extraction (`can:*` middleware), route-model-binding and Eloquent
  relationship detection (best-effort — only relationship methods with a
  declared return type are visible), and a fixed GET/POST/PUT-PATCH/DELETE
  risk classification (guidance only, not a security guarantee).

### Fixed

- Connected-mode trace push no longer blocks the traced request's own
  response. Previously synchronous and inline, up to 2s of latency could
  be added to every traced request if Flow API was slow or unreachable;
  the push is now deferred via `dispatch(...)->afterResponse()`, running
  after the response is already on its way back to the client.
- Exception capture, previously dead code: a thrown exception never
  reached `CaptureFlowTrace`'s own `catch` block, because
  `Illuminate\Routing\Pipeline` (the pipeline the `web`/`api` middleware
  groups run through) renders any exception to a response at the exact
  pipe it's thrown from — confirmed via a live reproduction. Fixed by
  hooking Laravel's own `ExceptionHandler::reportable()`, the extension
  point upstream of that swallowing; a real error now correctly shows
  `result.status: "error"` with the exception type/message recorded,
  instead of silently looking like a success.

## [0.1.0] — 2026-07-22

First tagged release.

### Added

- Zero-config request/controller/database/external/service instrumentation
  — no code changes required in the host app for any of the five node
  types (`Zerethon\Flow\Laravel\Providers\FlowServiceProvider`).
- Automatic service-layer tracing proxy: classes under configured
  namespaces (defaults to the whole `app/` tree) are wrapped in a timing
  proxy the moment the container resolves them — covers classes resolved
  via constructor injection, `app()->make()`, or interface binding; a
  manually `new`'d instance is not covered.
- Offline mode: every captured trace is written locally to
  `.zerethon/flow-history.json`, always, with no external network call.
- Connected mode: traces additionally pushed over HTTPS to a configured
  Flow API project (`FLOW_SERVER`/`FLOW_PROJECT_ID`/`FLOW_SECRET_KEY`),
  authenticated per-project. Falls back to Offline-only if any of the
  three env vars are missing.
- `php artisan flow:install` — validates Connected-mode credentials
  against the configured Flow API before relying on them.
- `php artisan flow:cache-services` / `flow:clear-services-cache` —
  pre-compute (or clear) the auto-traced-service class manifest, avoiding
  a filesystem walk on every boot outside Octane.
- Sensitive-data masking on by default (`options.mask_sensitive_data`):
  masks sensitive-named query-string/route-parameter values and
  email-shaped strings in captured URLs and manually-supplied trace meta.
- Configurable sample rate, excluded routes, and per-source
  enable/disable (`sources.request`/`controller`/`database`/`external`/`service`).
- Push-failure observability: a rejected or unreachable Connected-mode
  push logs a warning in the host app's own log channel instead of
  failing silently.

### Notes

- `except_environments` defaults to `['testing']` — Flow runs in every
  other environment by default, including production, since Connected
  mode's purpose is observing real production traffic.
