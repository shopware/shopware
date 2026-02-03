---
title: Typed attribute entity compilation
date: 2026-02-03
area: core
tags: [dal, attribute-entity, type-safety, extensibility]
---

## Context

The attribute-based entity system allows defining DAL entities using PHP 8 attributes instead of traditional `EntityDefinition` classes. This system is designed for plugin developers and will be considered for core usage once feature parity is reached.

The previous implementation passed field metadata as untyped arrays (`array<string, mixed>`) throughout the compilation pipeline, deferring errors to runtime. Adding new field types required modifying the central `AttributeEntityCompiler`, and attribute-based definitions were identified by hardcoded class name checks.

## Decision

### Typed Metadata Classes

Replace untyped arrays with immutable metadata classes:

| Class             | Purpose                                                      |
|-------------------|--------------------------------------------------------------|
| `EntityMetadata`  | Entity-level info: name, class, collection, hydrator, fields |
| `FieldMetadata`   | Field-level info: class, property name, attribute, flags     |
| `FlagMetadata`    | Flag class and constructor arguments                         |
| `MappingMetadata` | ManyToMany mapping table definition                          |

Each class validates its inputs at construction time and provides `toDefinition()` for Symfony container serialization.

### Field Creation in Attributes

Move field creation responsibility from the compiler into individual attributes:

```php
abstract class AbstractField
{
    abstract public static function fromArray(array $data): AbstractField;
    abstract public function toDefinition(): Definition;
    abstract public function getFieldClass(): string;

    public function createField(string $propertyName, string $column, string $entityName, ?string $propertyType = null): DalField;
}
```

Each field attribute (e.g., `ManyToOne`, `Serialized`) implements these methods, encapsulating type-specific logic. New field types can be added by extending `AbstractField` without modifying the compiler.

### Marker Interface

Introduce `AttributeBasedEntityDefinition` marker interface to distinguish attribute-based definitions from traditional `EntityDefinition` classes.

Traditional definitions can be instantiated to retrieve the entity name:
```php
$instance = new ProductDefinition();
$entityName = $instance->getEntityName();
```

Attribute-based definitions require `EntityMetadata` injection and cannot be instantiated during compilation. To avoid reconstructing metadata objects, the entity name is stored in the service tag:
```php
$definition->addTag('shopware.entity.definition', ['entity' => $entityName]);
```

The marker interface allows compiler passes (e.g., `SalesChannelEntityCompilerPass`) to detect which definitions provide entity names via tag data versus instantiation.

### Unknown Field Types

Unknown field types now throw `DataAbstractionLayerException::unknownFieldAttributeType()` instead of silently falling back to `StringField`. Silent fallbacks mask configuration errors that should be caught during development.

## Consequences

### Backwards Compatibility

- Public attribute constructor signatures remain unchanged
- Existing field attributes extend `Field`, which provides default implementations of the abstract methods
- No migration required for existing attribute-based entities

### Internal Methods

The `AbstractField` methods (`fromArray`, `toDefinition`, `createField`, `getFieldClass`) are used by the compilation pipeline. Developers creating custom field attributes must implement these methods but should not use them themself.
