---
title: Centralize entity name resolution in compiler passes
date: 2026-02-07
area: core
tags: [dependency-injection, compiler-pass, entity-definition, dal]
---

## Context

During DI container compilation, both `EntityCompilerPass` and `SalesChannelEntityCompilerPass` need to know the entity name for each service tagged with `shopware.entity.definition` or `shopware.sales_channel.entity.definition`. Both passes resolved entity names by instantiating the definition class and calling `getEntityName()`. This caused several problems:

* Every downstream pass independently instantiated the same definition classes, duplicating work and coupling compilation to constructor signatures.
* Attribute-based definitions (`AttributeEntityDefinition`, `AttributeTranslationDefinition`, `AttributeMappingDefinition`) require constructor arguments, so both passes had to hard-code class-name checks: `EntityCompilerPass` skipped them entirely (they were wired separately by `AttributeEntityCompilerPass` through runtime `register()` method calls), while `SalesChannelEntityCompilerPass` special-cased them by extracting constructor arguments from the service definition to instantiate them.
* Adding a new definition type with constructor dependencies would require updating every downstream pass that instantiates definitions — a fragile coupling.

The root issue is that entity name resolution was scattered across multiple passes instead of being a single, early normalization step.

## Decision

We introduce `EntityDefinitionTagCompilerPass`, a new compiler pass that runs once at priority 50 (after `AttributeEntityCompilerPass` at 99, before `EntityCompilerPass` and `SalesChannelEntityCompilerPass` at default priority). This pass normalizes entity names onto the `entity` attribute of each definition's service tag, so downstream passes can read the name from the tag without instantiating the class.

The resolution strategy:

1. **Tag already has `entity` attribute** (set by `AttributeEntityCompilerPass` for attribute-based definitions): if the class can be instantiated, the pass verifies that the tag value matches `getEntityName()`. A mismatch is a deprecation in v6.7 (tag kept as-is) and throws `entityTagMismatch()` in v6.8. If the class cannot be instantiated, the tag value is trusted as-is.
2. **Tag is missing `entity` attribute**: instantiate the class, call `getEntityName()`, and write the result to the tag. If the class cannot be instantiated (private constructor, required parameters), a deprecation is emitted in v6.7 (service skipped) and `entityTagUnresolvable()` is thrown in v6.8.

A class is considered instantiatable during compilation when it has a public constructor with zero parameters. A constructor with optional parameters is still treated as non-instantiatable. This covers all standard `EntityDefinition` subclasses.

After this pass runs, downstream passes read entity names exclusively from tags. Both `EntityCompilerPass` and `SalesChannelEntityCompilerPass` retain a deprecation fallback for v6.7: if the `entity` tag attribute is still missing (because `EntityDefinitionTagCompilerPass` skipped the service during the deprecation period), they emit a deprecation warning and resolve the entity name via instantiation. In v6.8, the missing attribute throws `missingEntityTagAttribute()`.

```php
// EntityCompilerPass — reads entity name from tag with deprecation fallback
$entity = $tags[0]['entity'] ?? null;
if ($entity === null || $entity === '') {
    /** @deprecated tag:v6.8.0 - remove else branch, keep only the throw */
    if (Feature::isActive('v6.8.0.0')) {
        throw DependencyInjectionException::missingEntityTagAttribute(...);
    }
    Feature::triggerDeprecationOrThrow('v6.8.0.0', ...);
    $entity = (new $class())->getEntityName();
}
```

`SalesChannelEntityCompilerPass::formatData()` is simplified from a method that received pre-resolved tag data and managed fallback aliases, to a self-contained tag reader:

```php
// before: received pre-resolved tag array, instantiated AttributeEntityDefinition
//         with constructor args, maintained 'fallBack' aliases
private function formatData(array $taggedServiceIds, ContainerBuilder $container): array

// after: resolves tags internally, reads entity name from tag attribute
private function formatData(ContainerBuilder $container, string $tagName): array
```

The `fallBack` alias logic is removed because the `entity` tag attribute now holds the canonical entity name for all definition types, making the alias indirection unnecessary.

`AttributeEntityCompilerPass` is updated to write the `entity` attribute when creating tags for attribute-based definitions, translation definitions, and mapping definitions.

### Compiler pass execution order

| Priority | Pass                              | Responsibility                                                     |
|----------|-----------------------------------|--------------------------------------------------------------------|
| 99       | `AttributeEntityCompilerPass`     | Registers attribute-based definitions, writes `entity` to tags     |
| 50       | `EntityDefinitionTagCompilerPass` | Normalizes `entity` onto all remaining tags, validates consistency |
| 0        | `EntityCompilerPass`              | Wires repositories, registry maps, autowiring aliases              |
| 0        | `SalesChannelEntityCompilerPass`  | Wires sales channel repositories and extensions                    |

### Deprecation strategy

All new validation errors follow the `Feature::isActive('v6.8.0.0')` pattern:

* **v6.7 (deprecation period)**: a deprecation warning is emitted via `Feature::triggerDeprecationOrThrow()`, and the pass falls back to the previous behavior (instantiation or skipping). This preserves backward compatibility for third-party services that have not yet added the `entity` tag attribute.
* **v6.8**: the domain exception is thrown immediately, and the fallback code is removed.

Each deprecation site is annotated with `@deprecated tag:v6.8.0 - remove else branch, keep only the throw` so that the v6.8 cleanup is mechanical.

### Error handling

Three new exception factory methods across two `DependencyInjectionException` classes:

On `Shopware\Core\Framework\DependencyInjection\DependencyInjectionException`:

* `entityTagMismatch()` — tag says one entity name, `getEntityName()` returns another. Catches misconfigured service definitions. Deprecation in v6.7, exception in v6.8.
* `entityTagUnresolvable()` — definition has no `entity` tag attribute and cannot be instantiated to resolve it. Forces developers to declare the entity name explicitly. Deprecation in v6.7 (service skipped), exception in v6.8.
* `missingEntityTagAttribute()` — definition reaches `EntityCompilerPass` without an `entity` tag attribute. During the deprecation period this can happen if `EntityDefinitionTagCompilerPass` skipped the service. Deprecation in v6.7 (falls back to instantiation), exception in v6.8.

On `Shopware\Core\System\DependencyInjection\DependencyInjectionException`:

* `missingEntityTagAttribute()` — services that reach `SalesChannelEntityCompilerPass` without an entity tag. Deprecation in v6.7 (falls back to instantiation), exception in v6.8.

## Extendability

Plugin and app developers registering custom `EntityDefinition` subclasses via `shopware.entity.definition` tags can optionally provide the `entity` attribute directly in their service definition XML:

```xml
<service id="MyPlugin\Core\Content\MyEntity\MyEntityDefinition">
    <tag name="shopware.entity.definition" entity="my_entity"/>
</service>
```

This is not required for standard definitions with parameterless constructors — the tag compiler pass resolves the name automatically. However, definitions with constructor dependencies must declare the `entity` attribute explicitly, otherwise the pass throws `entityTagUnresolvable`. This gives plugin developers a clear, self-documenting way to register entities without relying on runtime instantiation during compilation.

## Consequences

### For the platform

* Entity name resolution happens exactly once during compilation, in a single pass. Downstream passes read entity names from tags, with a deprecation fallback to instantiation in v6.7 that is removed in v6.8.
* Attribute-based definitions retain the skip-by-class-name check in `EntityCompilerPass` (`in_array($class, [AttributeEntityDefinition::class, ...])`) because they are fully wired by `AttributeEntityCompilerPass` (repositories, registry maps). The `compile()` and `setPublic()` calls are still applied to them, but entity name map population, repository creation, and autowiring alias registration are skipped. `SalesChannelEntityCompilerPass` now reads their entity names from tags instead of instantiating them with constructor arguments.
* The `fallBack` alias system in `SalesChannelEntityCompilerPass` is removed.
* Mismatch validation catches bugs early: if a service tag declares `entity="foo"` but the class returns `"bar"` from `getEntityName()`, a deprecation is emitted in v6.7 and compilation fails in v6.8 instead of silently registering under the wrong name.

### For third-party developers

* No migration effort for standard entity definitions — the tag compiler pass resolves entity names automatically from classes with parameterless constructors, which covers all conventional `EntityDefinition` subclasses.
* In v6.7, services without the `entity` tag attribute trigger deprecation warnings but continue to work via the instantiation fallback. In v6.8, the fallback is removed and the missing attribute throws an exception.
* Definitions with constructor dependencies (uncommon outside attribute-based entities) already could not be compiled — the old passes called `new $class()` unconditionally for non-attribute types, which would cause a PHP fatal error. The new code replaces that unhandled crash with a clear `entityTagUnresolvable` exception that names the service and class, and offers a resolution path: declare `entity="..."` on the service tag.
* The `entity` tag attribute now serves as the canonical entity name across all compiler passes.
