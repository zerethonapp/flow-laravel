# Release Process

`flow-laravel` has no `"version"` field in `composer.json` — Composer and
Packagist both resolve versions from git tags, not from the manifest.
There's no CI/CD pipeline that cuts releases automatically; this is a
manual process.

## Cutting a release

1. Make sure `main` is green: `./vendor/bin/phpunit` passes, and the
   `tests.yml` GitHub Actions workflow is green on the commit you're
   tagging.
2. Update [CHANGELOG.md](CHANGELOG.md): move `[Unreleased]` entries under a
   new `## [x.y.z] — YYYY-MM-DD` heading, following
   [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Commit this
   on its own (`Prepare vX.Y.Z release`).
3. Tag it, following [Semantic Versioning](https://semver.org/):
   ```bash
   git tag -a vX.Y.Z -m "vX.Y.Z"
   git push origin main
   git push origin vX.Y.Z
   ```
4. If Packagist auto-update (GitHub webhook) is configured, the new
   version appears within a minute or two. Otherwise, trigger a manual
   update from the package's page on packagist.org.
5. Create a GitHub Release from the pushed tag, pasting the matching
   CHANGELOG.md section as the release notes.

## Versioning notes (0.x)

This package is pre-1.0 — per SemVer, breaking changes may land in a minor
version bump (`0.x.0`), not just a major one. Once the adapter's public
API (config shape, `Flow::trace()`/`traceService()`/`traceExternal()`,
`flow:install`) is considered stable, the first `1.0.0` release should be
called out explicitly in the CHANGELOG as the point that promise starts.
