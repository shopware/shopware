---
title: Typed attribute entity compilation
date: 2026-02-03
area: core
tags: [dal, attribute-entity, type-safety, extensibility]
---

## Context

The attribute-based entity system allows defining DAL entities using PHP 8 attributes instead of class-based `EntityDefinition` classes. This system is designed for plugin developers and will be considered for core usage once feature parity is reached.

The goal of this refactoring is to improve type safety during compilation, make the system easier to extend with new field types, and catch configuration errors at build time rather than runtime.

## Domains

This ADR touches four areas:

- DAL compilation pipeline (attribute parsing and metadata creation)
- DI container integration (service definition serialization)
- Entity definition identification (how compiler passes detect definition types)
- Field type resolution (mapping attribute declarations to DAL fields)

## Problems

### DAL Compilation

The compiler passes field metadata as untyped arrays (`array<string, mixed>`) between compilation and runtime. Type errors in field configuration only surface when the entity is first used, which makes debugging difficult. The `AttributeEntityCompiler` class is the only place that understands how to interpret these arrays.

### DI Container Integration

Symfony's container dumper cannot serialize arbitrary objects. The previous implementation worked around this by converting everything to arrays, but this loses type information and validation. When the container is loaded from cache, there is no guarantee the array structure is correct.

### Entity Definition Identification

Several compiler passes need to know the entity name for a definition. Class-based definitions can be instantiated (`new ProductDefinition()`) to call `getEntityName()`. Attribute-based definitions require `EntityMetadata` injection and cannot be instantiated during compilation. The current workaround checks class names directly, which is fragile.

### Field Type Resolution

Adding a new field type requires modifying the central `AttributeEntityCompiler` to add another case to the field creation logic. Unknown field types silently fall back to `StringField`, which masks configuration errors.

## Solutions

### Typed Metadata Classes

Replace untyped arrays with immutable metadata classes that validate their inputs at construction time:

| Class             | Purpose                                                      |
|-------------------|--------------------------------------------------------------|
| `EntityMetadata`  | Entity-level info: name, class, collection, hydrator, fields |
| `FieldMetadata`   | Field-level info: class, property name, attribute, flags     |
| `FlagMetadata`    | Flag class and constructor arguments                         |
| `MappingMetadata` | ManyToMany mapping table definition                          |

Each class implements `toDefinition()` which creates a factory-based Symfony Definition. The factory receives a plain array and reconstructs the typed object. This approach satisfies Symfony's serialization requirements while providing type safety at both compile time and runtime.

Typed metadata was chosen over keeping arrays because it makes invalid states unrepresentable. A `FieldMetadata` object cannot exist with an invalid field class, while an array can contain anything.

### Field Creation in Attributes

Move field creation responsibility from the compiler into individual field attributes. Each attribute knows how to create its corresponding DAL field, including any type-specific logic like enum handling or serializer configuration.

This follows the open-closed principle: new field types can be added by creating new attribute classes without modifying existing code. The compiler only needs to know about `AbstractField`, not every possible field type.

### Marker Interface

Introduce `AttributeBasedEntityDefinition` as a marker interface. Compiler passes can use `instanceof` checks to determine whether a definition provides its entity name via tag data or instantiation.

The entity name is stored in the service tag during compilation:
```php
$definition->addTag('shopware.entity.definition', ['entity' => $entityName]);
```

A marker interface was chosen over a base class because attribute-based definitions already extend `AttributeEntityDefinition`. Using an interface avoids diamond inheritance issues and keeps the type hierarchy simple.

### Strict Field Type Validation

Unknown field types now throw `DataAbstractionLayerException::unknownFieldAttributeType()`. Silent fallbacks to `StringField` would defer bugs to runtime where they are harder to diagnose. Failing early during container compilation makes the error obvious and provides a clear message about what field type is missing.

## Comparison with Class-Based Definitions

Class-based definitions have no compilation phase. The container registers the class as a service, and `defineFields()` instantiates fields directly from hardcoded PHP.

Attribute-based definitions move configuration parsing to compilation. The container stores serialized metadata which is reconstructed at runtime to instantiate fields.

## Affected Logic

New files:

- `src/Core/Framework/DataAbstractionLayer/AttributeBasedEntityDefinition.php` (marker interface)
- `src/Core/Framework/DataAbstractionLayer/CompiledDefinitions.php`
- `src/Core/Framework/DataAbstractionLayer/EntityMetadata.php`
- `src/Core/Framework/DataAbstractionLayer/FieldMetadata.php`
- `src/Core/Framework/DataAbstractionLayer/FlagMetadata.php`
- `src/Core/Framework/DataAbstractionLayer/MappingMetadata.php`

Modified files:

- `src/Core/Framework/DataAbstractionLayer/AttributeEntityCompiler.php`
- `src/Core/Framework/DataAbstractionLayer/AttributeEntityDefinition.php`
- `src/Core/Framework/DataAbstractionLayer/AttributeTranslationDefinition.php`
- `src/Core/Framework/DataAbstractionLayer/AttributeMappingDefinition.php`
- `src/Core/Framework/DataAbstractionLayer/DataAbstractionLayerException.php`
- `src/Core/Framework/DataAbstractionLayer/DefinitionValidator.php`
- `src/Core/Framework/DataAbstractionLayer/Attribute/AbstractField.php`
- `src/Core/Framework/DataAbstractionLayer/Attribute/Field.php`
- All field attributes (`ManyToOne.php`, `OneToMany.php`, `ManyToMany.php`, `OneToOne.php`, `ForeignKey.php`, `Serialized.php`, `State.php`, `Translations.php`, `AutoIncrement.php`, `CustomFields.php`, `Version.php`, `ReferenceVersion.php`)
- `src/Core/System/DependencyInjection/CompilerPass/SalesChannelEntityCompilerPass.php`

## Extendability

Plugin developers can create custom field attributes for specialized DAL field types. A payment provider plugin might need a field that stores encrypted card tokens with automatic decryption on read. A PIM integration might need fields that sync with external systems.

To add a custom field attribute:

1. Create an attribute class extending `AbstractField`
2. Implement `fromArray()`, `toDefinition()`, `getFieldClass()`, and `createField()`
3. Register the attribute class in `AttributeEntityCompiler::FIELD_ATTRIBUTES`

Custom flags do not require any special handling. The `FlagMetadata` class works with any flag that follows the standard constructor pattern.

## Consequences

Public attribute constructor signatures remain unchanged. Existing field attributes extend `Field`, which provides default implementations of the abstract methods. No migration is required for existing attribute-based entities.

The `AbstractField` methods (`fromArray`, `toDefinition`, `createField`, `getFieldClass`) are internal implementation details of the compilation pipeline. Developers creating custom field attributes must implement these methods but should not call them directly.

The strict field type validation is a breaking change for any code that relied on the silent `StringField` fallback. Such code was likely already broken in subtle ways.

## Pseudocode

Compilation phase:

```
AttributeEntityCompilerPass::process(container)
    for each class with #[Entity] attribute:
        metadata = AttributeEntityCompiler::compile(class)

        for each property with field attribute:
            fieldMetadata = new FieldMetadata(
                class: attribute.getFieldClass(),
                propertyName: property.name,
                attribute: attribute,
                flags: compileFlags(property)
            )
            metadata.fields.add(fieldMetadata)

        container.setDefinition(entityName, metadata.toDefinition())
```

Runtime phase:

```
AttributeEntityDefinition::defineFields()
    fields = new FieldCollection()

    for each fieldMetadata in entityMetadata.fields:
        dalField = fieldMetadata.attribute.createField(
            propertyName,
            column,
            entityName,
            propertyType
        )

        for each flagMetadata in fieldMetadata.flags:
            dalField.addFlag(flagMetadata.createFlag())

        fields.add(dalField)

    return fields
```
