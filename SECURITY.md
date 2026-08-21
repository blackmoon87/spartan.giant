# Security Policy

## Supported versions

Spartan is pre-1.0. Security fixes land on `main` and in the latest `0.x` release.

| Version | Supported |
|---------|-----------|
| 0.x (latest) | ✅ |
| older 0.x | ❌ |

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Report privately through GitHub's
[private vulnerability reporting](https://github.com/blackmoon87/spartan/security/advisories/new)
on this repository. If that is unavailable to you, open a public issue containing
only the words "security report — please contact me" and no details, and a
maintainer will arrange a private channel.

Please include:

- the affected version or commit,
- what an attacker gains (the impact, not just the defect),
- a minimal reproduction — a route, request, or code snippet.

### What to expect

| Stage | Target |
|-------|--------|
| Acknowledgement | 72 hours |
| Initial assessment | 7 days |
| Fix or mitigation plan | 30 days for high severity |

Spartan is maintained by a small team, so these are targets rather than
guarantees. You will get an honest status update rather than silence.

Reporters are credited in the release notes unless they ask otherwise.

## Scope

In scope: the framework itself — everything under `framework/src`.

Out of scope:

- the example applications under `examples/`, which are demonstrations and
  deliberately ship with seeded credentials,
- misconfiguration of an application built with Spartan (`APP_DEBUG=true` in
  production, a world-readable `.env`, no `TRUSTED_PROXIES` behind a proxy),
- findings from automated scanners with no demonstrated impact.

## Hardening notes for applications

These are the settings that most often cause real-world incidents:

- **`APP_DEBUG=false` in production.** Debug mode prints exception messages and
  stack traces to the browser.
- **Set `TRUSTED_PROXIES` only if you are behind a proxy.** Left empty (the
  default), `X-Forwarded-For` is ignored and `REMOTE_ADDR` is authoritative, so
  clients cannot forge an IP to escape rate limiting.
- **Serve only `public/`.** Everything else — `.env`, `storage/`, `framework/`,
  `config/` — must sit outside the document root.
- **Call `Session::regenerate()` immediately after login** and any privilege
  change, to close session-fixation windows.
- **Keep the CSRF middleware on state-changing routes.** It covers POST, PUT,
  PATCH and DELETE; exclusions via `excludeCsrf()` are opt-in per path.
- **Pass user input as values, never as identifiers.** Values are bound;
  column names and operators are checked against a whitelist and will throw
  rather than interpolate.
