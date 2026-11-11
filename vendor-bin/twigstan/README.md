# Experimental Storefront analysis

This tool runs TwigStan against Shopware's test Twig environment and an initialized database.
Its dependencies and collector tests are isolated from the root Composer installation.
PHP 8.4 is required.
Root Composer hooks install and update only the standard tools; install this optional analyzer explicitly.

## Run locally

Use the worktree's Docker web container for these commands.

```sh
composer install --working-dir=vendor-bin/twigstan
composer lint:twigstan
```

The lint command returns failure when it finds template diagnostics.
The reporting command used by CI completes successfully with diagnostics and fails on incomplete analysis:

```sh
php src/Core/DevOps/StaticAnalyze/phpstan-bootstrap.php
php vendor-bin/twigstan/bin/report.php
```

The report is written to `var/twigstan-report/`.
It includes diagnostics, collected contexts, source-template coverage, timing and the dependency lock file.
The launchers first discover registered render listeners from the analysis container and infer their `setParameter()` value types with PHPStan.
Conditional writes contribute optional fields, and listener priorities determine which writes replace earlier values.
Controller route and scope listeners are applied at the render-event bridge, including constant template restrictions inferred from listener guards.
The listener summary is a tracked dependency of controller analysis and is included in the report as `listener-context.json`.
Dynamic parameter names, event aliases, unresolved template restrictions and event mutations delegated to helpers are reported as unresolved inference.
Inherited source templates are counted through their analyzed child templates.
`StorefrontTemplateContracts.php` declares typed entrypoints for nine reusable or legacy templates without a current core caller.
These methods are never registered as routes or executed.

Coverage describes which source templates reach analysis.
The two address-manager contexts inherited from `Struct::getVars()` still use a generic array type and have weaker member checks.
TwigStan 0.2.4 keeps unused loop-parent snapshots outside the context array to avoid exponential context growth.
Findings require triage before becoming blocking checks.

## Analyzer versions

- [TwigStan 0.2.4](https://github.com/twigstan/twigstan/releases/tag/0.2.4) is installed from upstream and pinned in `composer.lock`.
  It includes the loop-context fix for [issue #317](https://github.com/twigstan/twigstan/issues/317).
- PHPStan 2.3.5 is installed from the official Composer package and pinned in `composer.lock`.

The launcher runs the isolated Composer installation's PHPStan and forwards the tool's autoloader to parallel workers.
PHPDoc parser 2.4.1 matches the parser bundled in PHPStan.

## Temporary draft dependencies

This draft removes `shopware/conflicts` from the root and Core manifests to allow Twig 3.30.
The experimental CI job permits the current trunk dependency installation despite the advisory for its pinned MCP SDK, `PKSA-s52d-n7sx-trsg`.
That exception is limited to this job's temporary Composer configuration.
Restore the normal dependency restrictions before making the integration mergeable.

## Checks

```sh
php vendor-bin/twigstan/vendor/bin/phpunit --configuration vendor-bin/twigstan/phpunit.xml
php vendor-bin/twigstan/bin/phpstan analyse --configuration vendor-bin/twigstan/phpstan.neon
```

The completion guard tests cover successful analysis with findings, empty discovery, missing coverage and internal errors.
