---
title: Use runtime aliases for moved PHP classes
date: 2026-09-16
area: core
tags: [core, backwards-compatibility, deprecation, php]
---

## Context

Moving a supported PHP class to another namespace changes its fully qualified class name. Extensions may still use the previous name in type declarations, object construction, inheritance, static access, or as a dependency-injection service ID.

Compatibility subclasses at the previous namespace preserve some calls, but they create a second class identity and add another inheritance level. They can also duplicate implementations, diverge from the canonical class, and cannot transparently replace every moved class.

PHP's `class_alias()` keeps the previous and canonical names as the same runtime class. The alias must be registered before either name is first used, and static-analysis and backwards-compatibility tooling need structured information about the move.

## Decision

A moved supported class has one canonical declaration in its new namespace. The canonical class carries the repeatable `#[ClassMoved]` BC-change attribute with the version in which the previous name will be removed and the previous fully qualified class name.

The previous and canonical names are registered in `ClassAliasRegistry::ALIASES`.
The Composer bootstrap iterates over that authoritative list and registers every entry eagerly with `class_alias()` through `autoload.files`.
The registry also contains an uncalled private method with explicit `class_alias()` calls so PhpStorm can index the previous names.
Those declarations are only an IDE aid; forgetting one does not affect runtime registration or other tooling.
Core code uses only the canonical name.
If the class is a service, its previous service ID remains as a deprecated alias of the canonical service for the same transition period.

PHPStan validates both directions of the relationship: every `#[ClassMoved]` declaration has a matching registry entry, every registry entry has matching metadata, moved services have a deprecated service alias, and Core does not introduce new references to previous class names.

The backwards-compatibility checker reads the registry and compares the previous API against the canonical declaration.
This keeps class moves visible to the normal API comparison without maintaining a second class implementation.

## Consequences

Extensions can continue using a previous class name until the announced version, while both names resolve to the same runtime class. Reflection reports the canonical declaration, and code must not rely on the previous name being a distinct subclass.

Adding a class move requires coordinated metadata, an entry in `ClassAliasRegistry::ALIASES`, a deprecated dependency-injection alias when applicable, release information, and an upgrade entry for the removal version.
The registry entry, `#[ClassMoved]` attribute, and deprecated service alias are removed together in that version.

This mechanism is only for moving a supported class whose implementation survives. Classes that are removed or replaced with different behavior use the regular deprecation lifecycle instead.
