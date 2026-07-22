# Security Policy

## Supported Versions

Only the latest tagged release is supported with security fixes. There is
no formal LTS track yet — this package is at `0.x`, and breaking changes
may still occur between minor versions per [Semantic Versioning](https://semver.org/)'s
0.x conventions.

## Reporting a Vulnerability

Please do **not** open a public GitHub issue for a suspected security
vulnerability. Instead, email **security@zerethon.com** with:

- A description of the vulnerability and its potential impact.
- Steps to reproduce, or a proof-of-concept if you have one.
- The version of `flow-laravel` you're using.

We'll acknowledge your report and work with you on a fix and coordinated
disclosure timeline before any public write-up.

## Scope Notes

`flow-laravel` captures request/response metadata, database queries, and
service-layer execution timing from the host application. Two things worth
knowing if you're evaluating this for a security review:

- **Sensitive-data masking is on by default**
  (`options.mask_sensitive_data`) — sensitive-named query-string/route
  values and email-shaped strings are masked in captured URLs and
  manually-supplied trace metadata before they're written to
  `.zerethon/flow-history.json` or pushed in Connected mode. It does not
  cover raw SQL text (`capture_sql` is a separate, off-by-default option)
  or arbitrary exception message content — see
  `src/Instrumentation/Masker.php`'s own doc comment for exactly what is
  and isn't covered.
- **Connected mode authenticates via a per-project secret**
  (`FLOW_SECRET_KEY`), sent as a bearer token over HTTPS. Treat it like
  any other credential — do not commit it, and rotate it if you suspect
  it's leaked (contact security@zerethon.com if you need help with that;
  there is no self-service rotation endpoint yet).
