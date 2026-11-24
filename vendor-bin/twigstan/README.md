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
The recursive line-item template declares `nestingLevel` as `int` with Twig's `types` tag so analysis can converge without losing the other context types.
This declaration only provides type information and does not change the runtime value.
`GuardedAttributeIgnoreExtension` permits safe attribute probes even when the current context type cannot provide that attribute.
It filters `getAttribute.notFound` only for existence checks, lookups with strict checks disabled by Twig, and reads already narrowed by a guard.
Unguarded reads and other diagnostic identifiers remain checked.
Core's `FeatureGuardedDeprecationExtension` is shared with PHPStan and recognises deprecated API uses in legacy feature-flag branches of the generated PHP.
The API's removal version must match the major feature flag proven inactive by the guard.
Named flags can be combined with that major check, but do not prove it inactive on their own.
Direct conditions and dominating boolean aliases are recognised; aliases are invalidated when overwritten, unset or changed conditionally.
Literal PHP callbacks passed to `Feature::callSilentIfInactive()` and `Feature::ifNotActive()` also qualify for the matching removal version.
Enabled branches, unrelated versions, unknown or dynamic flags, and unguarded calls retain their deprecation diagnostics.
The adapter uses PHPStan's cached parser and does not change template execution or select a runtime flag configuration for analysis.
Findings require triage before becoming blocking checks.

Templates can opt into an input contract by placing `{## template-contract ##}` immediately before a `{% types %}` declaration at the template's entry point.
For static Twig includes, the analysis visitor preserves the original arguments in a contract context expression.
`TemplateContractRule` validates these arguments before the stock include collector exports the declared context for analysis.
The declaration is embedded in generated PHP so a contract change invalidates cached caller analysis.
Required inputs must be definitely present, optional inputs may be absent, and concrete supplied types must fit the declaration.
Unknown `mixed` values remain accepted as unverified inputs.
Extra caller variables remain available at runtime but are excluded from the contracted template's analysis; genuine Twig globals remain available.
Templates without the marker retain ordinary inference.
This validation currently applies to Twig include calls; PHP render entrypoints retain their existing inference.

Use `{## external-interface ##}` before a `types` declaration for a partial interface that checks its declared inputs while preserving the other inferred caller fields.
This marker uses the same caller validation and accepts extra variables in analysis as well as at runtime.
The ordinary `{## template-contract ##}` marker continues to project the analysis context to its declaration when a complete interface is needed to prevent context-shape degradation.

```twig
{## external-interface ##}
{% types {
    listSearchTerm?: 'string|null'
} %}
```

Declare supported optional interface inputs with `?` in the `types` mapping, including inputs that no current core caller supplies.
Caller inference alone cannot describe extension-facing inputs that core does not exercise, and analysis may otherwise treat their guarded branches as unreachable or report missing variables inside them.
Use a caller-validated interface declaration for these inputs so concrete supplied values are checked against their declared types.
Keep computed local variables inferred from their assignments; optional input declarations must not mask lost block context.

## Analyzer versions

- [TwigStan 0.2.8](https://github.com/twigstan/twigstan/releases/tag/0.2.8) is installed from upstream and pinned in `composer.lock`.
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
