# Type

Element type system. Declarative type definitions for content elements — what types exist, what properties they have, what slots they provide. Types are defined via YAML files and discovered from core, bundles, plugins, and apps.

## Guides

- [docs/output-schema.md](docs/output-schema.md) - Why the type spec describes hydrated output, and the property key that links it to elements and loaders.
- [docs/architecture.md](docs/architecture.md) - Value objects, loaders, registry, compiler pass, and app integration.
- [docs/custom-types.md](docs/custom-types.md) - The plugin- and app-facing authoring guide.
- [docs/introspection.md](docs/introspection.md) - The Admin API endpoint clients read to discover the registered types.

## Inline `bindings:` Sections

A type YAML file may carry a top-level `bindings:` key that the binding system reads through `Binding/Loader/YamlBindingSpecificationLoader`, as [failure-and-loss.md](../../docs/principles/failure-and-loss.md#a-component-throws-where-it-meets-invalid-data-and-nothing-degrades-silently) states. A reference property's `resolvedBy` key needs no `bindings:` section. See [Binding/README.md](../../Binding/README.md).

## Subdirectories

- **Definitions/** - Core YAML type definitions, organized into category subdirectories
- **Loader/** - Type loading: `AbstractContentSystemElementTypeLoader` (base), `YamlTypeLoader` (filesystem), `DatabaseTypeLoader` (app types in prod), `ElementTypeNameResolver` (path-to-name), `ElementTypeSourceDirectory` (source directory VO), `ResolvedElementTypeSpecificationDto` (loading-to-spec bridge)
- **Registry/** - AbstractContentSystemElementTypeRegistry (decoration pattern contract), ContentSystemElementTypeRegistry (stateless aggregator), CachedContentSystemElementTypeRegistry (cross-request cache decorator)
- **Serialization/** - ElementTypeSpecificationSerializer (YAML ↔ DTO conversion)
- **Specification/** - Value objects (ContentSystemElementTypeSpecification, PropertySpecification, SlotSpecification, CopilotSpecification)
- **Specification/Dto/** - Validation DTOs with Symfony constraint attributes
- **Validation/** - `ElementTypeCollisionDetector` (validates proposed names against registry + inactive app types), `TranslatableType` (translatable requires `type: string`), `TypedEnum` (enum type/list/values), `TypedDefault` (default type/value)
