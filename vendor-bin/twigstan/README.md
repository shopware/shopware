# Experimental Storefront analysis

This tool runs TwigStan against Shopware's test Twig environment and an initialized database.
Its dependencies and collector tests are isolated from the root Composer installation.
PHP 8.4 is required.

## Run locally

Use the worktree's Docker web container for these commands.

```sh
composer install --working-dir=vendor-bin/twigstan
sha256sum --check vendor-bin/twigstan/phpstan.phar.sha256
composer lint:twigstan
```

The lint command returns failure when it finds template diagnostics.
The reporting command used by CI completes successfully with diagnostics and fails on incomplete analysis:

```sh
php src/Core/DevOps/StaticAnalyze/phpstan-bootstrap.php
php vendor-bin/twigstan/bin/report.php
```

The report is written to `var/twigstan-report/`.
It includes diagnostics, collected contexts, source-template coverage, timing, the dependency lock file and PHAR provenance.
Inherited source templates are counted through their analyzed child templates.
`StorefrontTemplateContracts.php` declares typed entrypoints for nine reusable or legacy templates without a current core caller.
These methods are never registered as routes or executed.

Coverage describes which source templates reach analysis.
The two address-manager contexts inherited from `Struct::getVars()` still use a generic array type and have weaker member checks.
Recursive include ancestry is bounded for convergence, with the immediate parent retained precisely.
Findings require triage before becoming blocking checks.

## Public dependency forks

- [TwigStan experiment](https://github.com/keulinho/twigstan/tree/12069ced366b597928acd80fe2111e5850d1b550): context preservation, recursive convergence and separate resolver memoization.
  The exact revision is pinned in `composer.lock` through the fork's `0.2.x-dev` branch alias.
- [PHPStan experiment](https://github.com/keulinho/phpstan-src/tree/25f0ab3d8aefc6b5968bef28126507d0be282716): persistent array-shape preservation and the independent identical-array fast path.
  Their PHP implementation is backported to the committed PHPStan 2.3.5 PHAR.
  `patches/phpstan-2.3.5-phar.patch` records the exact archive-source changes.

The committed PHAR runs independently of Composer's PHPStan executable.
The launcher forwards the tool's autoloader to parallel workers.
Turbo is disabled until a matching native implementation is packaged.
PHPDoc parser 2.4.1 matches the parser bundled in the PHAR.
The MIT license is included in `PHPSTAN_LICENSE`.

Terminal-template reuse remains deferred.

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
