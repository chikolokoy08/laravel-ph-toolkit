# Security

## Reporting a vulnerability

Report it privately through GitHub:
[open a security advisory](https://github.com/chikolokoy08/laravel-ph-toolkit/security/advisories/new).

Do not open a public issue for a security problem.

Include what you found, how to reproduce it, and the version you were running.
A proof of concept helps but is not required.

This is a volunteer-maintained project. I aim to acknowledge reports within 7
days and will keep you updated as I work on a fix. If a fix is needed, it ships
as a patch release and the advisory is published once it is available.

## Scope

The core of this package reads the values you pass and returns values. It makes
no network calls and writes nothing. The things worth reporting are:

- Input that makes a validator or formatter throw instead of returning `false`
  or `null`.
- Input that causes unbounded memory use or makes a function hang, such as a
  pathological case in one of the patterns.
- Anything in the address endpoints or the Blade component that exposes more
  than the bundled PSGC data, or that puts unescaped input into a response.
- Anything in the published package that is not built from this repository.

Wrong data is a bug, not a vulnerability. A barangay under the wrong city, or a
valid mobile prefix that is rejected, belongs in a normal issue.

The address endpoints are off by default. If you turn them on, they serve the
bundled PSGC data to whoever can reach them, which is what they are for. Put
them behind the middleware your application needs; see
[docs/address-selector.md](docs/address-selector.md).

## Supported versions

The latest release. This project is pre-1.0, so fixes go into the current minor
version rather than being backported.
