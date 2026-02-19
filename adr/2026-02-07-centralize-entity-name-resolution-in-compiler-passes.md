---
title: Centralize entity name resolution in compiler passes
date: 2026-02-07
area: core
tags: [dependency-injection, compiler-pass, entity-definition, dal]
---

## Context

During DI container compilation, both `EntityCompilerPass` and `SalesChannelEntityCompilerPass` need to know the entity name for each service tagged with `shopware.entity.definition` or `shopware.sales_channel.entity.definition`. Both passes resolved entity names by instantiating the definition class and calling `getEntityName()`. This caused several problems:

* Every downstream pass independently instantiated the same definition classes, duplicating work and coupling compilation to constructor signatures.
* Attribute-based definitions require constructor arguments, so both passes had to special-case them with hard-coded class-name checks.
* Adding a new definition type with constructor dependencies would require updating every downstream pass — a fragile coupling.

The root issue is that entity name resolution was scattered across multiple passes instead of being a single, early normalization step.

## Decision

We introduce `EntityDefinitionTagCompilerPass`, a new compiler pass that runs once at priority 50 (after `AttributeEntityCompilerPass` at 99, before `EntityCompilerPass` and `SalesChannelEntityCompilerPass` at default priority). This pass normalizes entity names onto the `entity` attribute of each definition's service tag, so downstream passes can read the name from the tag without instantiating the class.

The resolution strategy:

1. **Tag already has `entity` attribute** (set by `AttributeEntityCompilerPass` for attribute-based definitions): if the class can be instantiated, the pass verifies that the tag value matches `getEntityName()`. A mismatch throws `entityTagMismatch()`. If the class cannot be instantiated (no public parameterless constructor), the tag value is trusted as-is.
2. **Tag is missing `entity` attribute**: instantiate the class, call `getEntityName()`, and write the result to the tag. If the class cannot be instantiated, throw `entityTagUnresolvable()`.

After this pass runs, downstream passes read entity names from tags instead of instantiating classes. `SalesChannelEntityCompilerPass::formatData()` is simplified to a tag reader, and its `fallBack` alias logic is removed.

`AttributeEntityCompilerPass` is updated to write the `entity` attribute when creating tags for attribute-based definitions, translation definitions, and mapping definitions.

All new validation errors follow the standard `Feature::isActive('v6.8.0.0')` deprecation pattern: deprecation warning with fallback in v6.7, exception in v6.8.

### Compiler pass execution order

| Priority | Pass                              | Responsibility                                                     |
|----------|-----------------------------------|--------------------------------------------------------------------|
| 99       | `AttributeEntityCompilerPass`     | Registers attribute-based definitions, writes `entity` to tags     |
| 50       | `EntityDefinitionTagCompilerPass` | Normalizes `entity` onto all remaining tags, validates consistency |
| 0        | `EntityCompilerPass`              | Wires repositories, registry maps, autowiring aliases              |
| 0        | `SalesChannelEntityCompilerPass`  | Wires sales channel repositories and extensions                    |

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

* Entity name resolution happens exactly once during compilation, in a single pass. Downstream passes read entity names from tags.
* The `fallBack` alias system in `SalesChannelEntityCompilerPass` is removed.
* Mismatch validation catches misconfigured tags early instead of silently registering under the wrong name.

### For third-party developers

* No migration effort for standard entity definitions — the tag compiler pass resolves entity names automatically from classes with parameterless constructors.
* In v6.7, services without the `entity` tag attribute trigger deprecation warnings but continue to work. In v6.8, the fallback is removed and the missing attribute throws an exception.
* The `entity` tag attribute serves as the canonical entity name across all compiler passes.
