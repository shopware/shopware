# Admin Plugin Compatibility Validation README

## Purpose

Use this local-only workflow to validate Administration migrations against the checked-out Commercial plugin at `custom/plugins/SwagCommercial`.

The workflow rejects `CI`, `GITHUB_ACTIONS`, and `GITLAB_CI` because Commercial sources, generated licenses, reports, and private artifacts must stay local.

## How To Start

Use this path for the first local run:

1. Confirm Commercial is checked out at `custom/plugins/SwagCommercial`.
2. Make sure `commercial-license-generator` is installed and available on your `PATH`, or use the bundled local wrapper with a downloaded dev license key file.
3. Make sure `.env` contains a reachable `APP_URL` for your local shop.
4. Pick the console command style for your setup.
5. Run the workflow.
6. Open the generated Markdown report under `var/admin-plugin-compatibility/reports/`.

Use this decision table if you are unsure which command to start with:

| Setup | Generator location | Command |
| --- | --- | --- |
| Host shop | Host `PATH` generator | `composer admin:plugin-compatibility -- --profile commercial` |
| Docker shop | Host `PATH` generator | `composer admin:plugin-compatibility -- --profile commercial --commercial-console-command "docker compose exec web bin/console"` |
| Docker shop | Bundled wrapper with dev license | `composer admin:plugin-compatibility -- --profile commercial --commercial-license-key-file var/admin-plugin-compatibility/dev-license.json --commercial-console-command "docker compose exec web bin/console"` |
| Docker shop | Web container `PATH` | `composer admin:plugin-compatibility -- --profile commercial --commercial-license-generator "docker compose exec web commercial-license-generator" --commercial-console-command "docker compose exec web bin/console"` |

`--commercial-console-command` controls how the generator and runner call Shopware's `bin/console`. `--commercial-license-generator` controls where the generator executable itself runs.
When both commands target the same Docker Compose service, the runner passes the container-local console command to the generator automatically.

If no private `commercial-license-generator` exists on `PATH`, the runner falls back to `scripts/admin-plugin-compatibility/bin/commercial-license-generator`. That wrapper cannot create signed Commercial licenses itself because Commercial validates the JWT signature against its bundled public key. Use it with a downloaded internal dev license key file, or install the internal generator on `PATH`.

The bundled wrapper accepts a raw license key file or a JSON artifact containing a `key` property:

```bash
composer admin:plugin-compatibility -- --profile commercial --commercial-license-key-file var/admin-plugin-compatibility/dev-license.json --commercial-console-command "docker compose exec web bin/console"
```

For manual setup, download the development license from the internal `feature-toggles` `dev_license.yml` workflow and set it with `bin/console commercial:license:set localhost <key>`.

For a normal local setup, start with:

```bash
composer admin:plugin-compatibility -- --profile commercial
```

For a Docker-based shop, start with:

```bash
composer admin:plugin-compatibility -- --profile commercial --commercial-console-command "docker compose exec web bin/console"
```

For a focused migration check, pass the affected component areas:

```bash
composer admin:plugin-compatibility -- --profile commercial --components sw-media-library,sw-settings-search
```

If the first full run is green on a known-good trunk commit, write a local baseline:

```bash
composer admin:plugin-compatibility -- --profile commercial --write-baseline
```

The baseline and reports stay under `var/admin-plugin-compatibility/` and are ignored by Git.

## Default Command

```bash
composer admin:plugin-compatibility -- --profile commercial
```

## Prerequisites

- `custom/plugins/SwagCommercial` exists and its `composer.json` package name is `shopware/commercial`.
- `commercial-license-generator` is available on `PATH`, passed with `--commercial-license-generator`, or the bundled wrapper gets a downloaded dev license via `--commercial-license-key-file`.
- `APP_URL` is set in `.env` for Playwright smoke tests.
- The local shop is installed and reachable.

## Useful Options

```bash
composer admin:plugin-compatibility -- --profile commercial --components sw-media-library,sw-settings-search
composer admin:plugin-compatibility -- --profile commercial --commercial-console-command "docker compose exec web bin/console"
composer admin:plugin-compatibility -- --profile commercial --commercial-license-key-file var/admin-plugin-compatibility/dev-license.json
composer admin:plugin-compatibility -- --profile commercial --commercial-license-host localhost --commercial-license-plan beyond
composer admin:plugin-compatibility -- --profile commercial --skip-build
composer admin:plugin-compatibility -- --profile commercial --write-baseline
```

## What It Does

1. Rejects CI environments.
2. Validates the local Commercial checkout.
3. Runs `plugin:refresh` and installs/activates `SwagCommercial` with `--skip-asset-build`.
4. Runs `npm ci --no-audit --prefer-offline` in Commercial only when `node_modules` is missing.
5. Runs `commercial-license-generator` with `--console`, `--host`, and `--plan` without `--debug` by default.
6. Clears cache and validates `core.store.licenseHost`.
7. Runs `composer build:js:admin` unless `--skip-build` is set.
8. Runs the isolated Playwright suite at `tests/acceptance/playwright.admin-plugin-compatibility.config.ts`.
9. Writes redacted JSON and Markdown reports to `var/admin-plugin-compatibility/reports/`.
10. Compares against `var/admin-plugin-compatibility/baseline/commercial.json` when present, or writes it when `--write-baseline` is set.

## Smoke Components

Initial component mappings:

- `sw-media-library` uses the Commercial media library smoke case.
- `sw-settings-search` uses the Commercial settings search smoke case.

Unknown `--components` values are reported as coverage gaps in the report instead of being silently ignored.

## Failure Classes

- `setup`: missing Commercial checkout, plugin install failure, missing license generator, or Commercial npm install failure.
- `license`: license generator failure, missing license host config, or invalid Commercial license state.
- `build`: Administration or Commercial bundle build failure during `composer build:js:admin`.
- `runtime`: Playwright smoke failure, runtime console error, page error, failed dynamic import, or failed Admin asset request.

## Reports And Artifacts

Reports are written below `var/admin-plugin-compatibility/reports/` and are ignored by Git. License keys, tokens, passwords, authorization values, private keys, and secret-like values are redacted before report writing.

Do not commit generated reports, generated license keys, `.store.json`, private plugin artifacts, or build output.

## Baseline

After the first green local run on a known-good trunk commit, write an ignored local baseline:

```bash
composer admin:plugin-compatibility -- --profile commercial --write-baseline
```

The baseline is stored under `var/admin-plugin-compatibility/baseline/`, which is ignored by Git. Review it locally for secrets before sharing any summary manually.
