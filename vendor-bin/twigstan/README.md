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
The shared Shopware PHPStan CMS extension infers slot data types from registered resolvers without executing them.
Both `composer phpstan` and the TwigStan launchers prepare the mapping automatically.
When invoking PHPStan directly, first run `php src/Core/DevOps/StaticAnalyze/cms-element-data.php` after the container bootstrap.
`CmsSlotEntity<TData>` preserves these types through `getFirstElementOfType()`, `getElementsOfType()` and `getData()`.
Inherited enrichment and local helpers are analyzed; unresolved mutations retain the general `Struct` type and are listed in `cms-element-context.json`.
The launchers discover registered render listeners from the analysis container and infer their `setParameter()` value types with PHPStan.
Conditional writes contribute optional fields, and listener priorities determine which writes replace earlier values.
Controller route and scope listeners are applied at the render-event bridge, including constant template restrictions inferred from listener guards.
The listener summary is a tracked dependency of controller analysis and is included in the report as `listener-context.json`.
Dynamic parameter names, event aliases, unresolved template restrictions and event mutations delegated to helpers are reported as unresolved inference.
Inherited source templates are counted through their analyzed child templates.
`StorefrontTemplateContracts.php` declares typed entrypoints for nine reusable or legacy templates without a current core caller.
These methods are never registered as routes or executed.

Coverage describes which source templates reach analysis.
`StructGetVarsReturnTypeExtension` preserves the declared types of visible properties exported by `Struct::getVars()`, including readable virtual properties.
Stored properties without defaults remain optional, and additional dynamic or subclass fields remain possible with unknown types.
Overrides such as `ArrayStruct::getVars()` retain their own return types.
The address-manager controller uses `array_merge()` because PHPStan 2.3.5 drops unknown additional keys when unpacking these shapes.
Object-valued fields retain their declared types; calling a setter does not narrow a `Page` property to a particular page subclass.
`AddressEditorModalStruct::$page` temporarily uses PHPDoc to retain `AddressListingPage|null` until the announced native type narrowing in 6.8.
TwigStan keeps unused loop-parent snapshots outside the context array to avoid exponential context growth.
The analysis visitor widens `nestingLevel` to `int` at the recursive line-item include so analysis can converge without losing the other context types.
The hint is injected during analysis because Ludtwig 0.14 does not recognize Twig's `types` tag.
Findings require triage before becoming blocking checks.

## Analyzer versions

- [TwigStan main](https://github.com/twigstan/twigstan/tree/main) is installed from upstream and pinned to a commit in `composer.lock`.
  It includes the loop-context fix for [issue #317](https://github.com/twigstan/twigstan/issues/317) and preserves block context through unrelated conditions as described in [issue #324](https://github.com/twigstan/twigstan/issues/324).
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
php vendor-bin/twigstan/bin/phpstan analyse --configuration vendor-bin/twigstan/phpstan.dist.neon
```

The completion guard tests cover successful analysis with findings, empty discovery, missing coverage and internal errors.
