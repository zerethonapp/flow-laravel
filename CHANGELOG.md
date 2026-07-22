# Changelog

All notable changes to `flow-laravel` are documented here. Format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows
[Semantic Versioning](https://semver.org/).

## [Unreleased]

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
