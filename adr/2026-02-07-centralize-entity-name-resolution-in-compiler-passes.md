---
title: Centralize entity name resolution in compiler passes
date: 2026-02-07
area: core
tags: [dependency-injection, compiler-pass, entity-definition, dal]
---

## Context

During DI container compilation, both `EntityCompilerPass` and `SalesChannelEntityCompilerPass` need to know the entity name for each service tagged with `shopware.entity.definition` or `shopware.sales_channel.entity.definition`. Until now, both passes resolved entity names by instantiating the definition class and calling `getEntityName()`. This caused several problems:

* Every downstream pass independently instantiated the same definition classes, duplicating work and coupling compilation to constructor signatures.
* Attribute-based definitions (`AttributeEntityDefinition`, `AttributeTranslationDefinition`, `AttributeMappingDefinition`) require constructor arguments, so both passes had to hard-code class-name checks: `EntityCompilerPass` skipped them entirely (they were wired separately by `AttributeEntityCompilerPass` through runtime `register()` method calls), while `SalesChannelEntityCompilerPass` special-cased them by extracting constructor arguments from the service definition to instantiate them.
* Adding a new definition type with constructor dependencies would require updating every downstream pass that instantiates definitions — a fragile coupling.

The root issue is that entity name resolution was scattered across multiple passes instead of being a single, early normalization step.

## Decision

We introduce `EntityDefinitionTagCompilerPass`, a new compiler pass that runs once at priority 50 (after `AttributeEntityCompilerPass` at 99, before `EntityCompilerPass` and `SalesChannelEntityCompilerPass` at default priority). This pass normalizes entity names onto the `entity` attribute of each definition's service tag, so downstream passes can read the name from the tag without instantiating the class.

The resolution strategy:

1. **Tag already has `entity` attribute** (set by `AttributeEntityCompilerPass` for attribute-based definitions): if the class can be instantiated, the pass verifies that the tag value matches `getEntityName()`. A mismatch throws `entityTagMismatch()`. If the class cannot be instantiated, the tag value is trusted as-is.
2. **Tag is missing `entity` attribute**: instantiate the class, call `getEntityName()`, and write the result to the tag. If the class cannot be instantiated (private constructor, required parameters), throw `DependencyInjectionException::entityTagUnresolvable()`.

A class is considered instantiatable during compilation when it has a public constructor with zero parameters. A constructor with optional parameters is still treated as non-instantiatable. This covers all standard `EntityDefinition` subclasses.

After this pass runs, downstream passes read entity names exclusively from tags:

```php
// EntityCompilerPass — before
$instance = new $class();
$entity = $instance->getEntityName();

// EntityCompilerPass — after
$entity = $tags[0]['entity'] ?? null;
if ($entity === null || $entity === '') {
    throw DependencyInjectionException::missingEntityTagAttribute(...);
}
```

`SalesChannelEntityCompilerPass::formatData()` is simplified from a method that instantiates definitions and manages fallback aliases to a tag reader:

```php
// before: instantiated AttributeEntityDefinition with constructor args,
//         maintained 'fallBack' aliases from tag['entity']
private function formatData(array $taggedServiceIds, ContainerBuilder $container): array

// after: reads entity name directly from tag
private function formatData(array $taggedServiceIds): array
```

The `ContainerBuilder` parameter and all `fallBack` alias logic are removed because the `entity` tag attribute now holds the canonical entity name for all definition types, making the alias indirection unnecessary.

`AttributeEntityCompilerPass` is updated to write the `entity` attribute when creating tags for attribute-based definitions, translation definitions, and mapping definitions.

### Compiler pass execution order

| Priority | Pass                              | Responsibility                                                     |
|----------|-----------------------------------|--------------------------------------------------------------------|
| 99       | `AttributeEntityCompilerPass`     | Registers attribute-based definitions, writes `entity` to tags     |
| 50       | `EntityDefinitionTagCompilerPass` | Normalizes `entity` onto all remaining tags, validates consistency |
| 0        | `EntityCompilerPass`              | Wires repositories, registry maps, autowiring aliases              |
| 0        | `SalesChannelEntityCompilerPass`  | Wires sales channel repositories and extensions                    |

### Error handling

Three new exception factory methods across two `DependencyInjectionException` classes:

On `Shopware\Core\Framework\DependencyInjection\DependencyInjectionException`:

* `entityTagMismatch()` — tag says one entity name, `getEntityName()` returns another. Catches misconfigured service definitions.
* `entityTagUnresolvable()` — definition has no `entity` tag attribute and cannot be instantiated to resolve it. Forces developers to declare the entity name explicitly.
* `missingEntityTagAttribute()` — definition reaches `EntityCompilerPass` without an `entity` tag attribute (should not happen if `EntityDefinitionTagCompilerPass` ran, but guards against misconfiguration).

On `Shopware\Core\System\DependencyInjection\DependencyInjectionException`:

* `missingEntityTagAttribute()` — services that reach `SalesChannelEntityCompilerPass` without an entity tag.

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

* Entity name resolution happens exactly once during compilation, in a single pass. Downstream passes no longer instantiate definition classes, removing a class of subtle coupling bugs.
* Attribute-based definitions retain the skip-by-class-name check in `EntityCompilerPass` (`in_array($class, [AttributeEntityDefinition::class, ...])`) because they are fully wired by `AttributeEntityCompilerPass` (repositories, registry maps). The `compile()` and `setPublic()` calls are still applied to them, but entity name map population, repository creation, and autowiring alias registration are skipped. `SalesChannelEntityCompilerPass` now reads their entity names from tags instead of instantiating them with constructor arguments.
* The `fallBack` alias system in `SalesChannelEntityCompilerPass` is removed.
* Mismatch validation catches bugs early: if a service tag declares `entity="foo"` but the class returns `"bar"` from `getEntityName()`, compilation fails with a clear error instead of silently registering under the wrong name.

### For third-party developers

* No migration effort for standard entity definitions — the tag compiler pass resolves entity names automatically from classes with parameterless constructors, which covers all conventional `EntityDefinition` subclasses.
* Definitions with constructor dependencies (uncommon outside attribute-based entities) already could not be compiled — the old passes called `new $class()` unconditionally for non-attribute types, which would cause a PHP fatal error. The new code replaces that unhandled crash with a clear `entityTagUnresolvable` exception that names the service and class, and offers a resolution path: declare `entity="..."` on the service tag.
* The `entity` tag attribute now serves as the canonical entity name across all compiler passes.
