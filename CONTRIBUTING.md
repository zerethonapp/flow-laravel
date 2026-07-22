# Contributing to flow-laravel

Thanks for considering a contribution. This package is a Laravel
instrumentation adapter for Flow — see the [README](README.md) for what it
does and `flow-docs/ADAPTER_PROTOCOL.md` (in the main Flow repo) for the
wire contract it implements, if you're touching anything that talks to
Flow API.

## Getting started

```bash
git clone https://github.com/zerethonapp/flow-laravel.git
cd flow-laravel
composer install
./vendor/bin/phpunit
```

There's no `composer test` script — run `./vendor/bin/phpunit` directly.
There's no Pint/style-linter configured in this package either; match the
existing code style in the file you're editing.

## Before opening a PR

- Add or update a test for any behavior change — `tests/` uses Orchestra
  Testbench (`tests/TestCase.php`), not a full Laravel app.
- Run the full suite (`./vendor/bin/phpunit`) and make sure it's green.
- Keep PRs scoped to one change — a bug fix shouldn't also carry an
  unrelated refactor.
- If you're changing anything under `Instrumentation/` or `Transport/`,
  check whether it affects the wire format documented in
  `flow-docs/ADAPTER_PROTOCOL.md` — if it does, that doc needs updating in
  the same PR (or a linked follow-up), not left to drift.

## Reporting bugs / requesting features

Use the GitHub issue templates. For anything that looks like a security
issue, see [SECURITY.md](SECURITY.md) instead — please don't open a public
issue for that.

## Code of Conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md).
