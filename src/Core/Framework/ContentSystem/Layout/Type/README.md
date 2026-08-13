# Type

Element type system. Declarative type definitions for content elements — what types exist, what properties they have, what slots they provide. Types are defined via YAML files and discovered from core, bundles, plugins, and apps.

## Guides

- [docs/output-schema.md](docs/output-schema.md) - Why the type spec describes hydrated output, and the property key that links it to elements and loaders.
- [docs/architecture.md](docs/architecture.md) - Value objects, loaders, registry, compiler pass, and app integration.
- [docs/custom-types.md](docs/custom-types.md) - The plugin- and app-facing authoring guide.
- [docs/introspection.md](docs/introspection.md) - The Admin API endpoint clients read to discover the registered types.

## Inline `bindings:` Sections

A type YAML file may carry a top-level `bindings:` key declaring binding specifications for its type inline. The key is reserved for the binding system and invisible to this pipeline: `ElementTypeSpecificationSerializer::denormalize()` reads only `meta`, `properties`, and `slots`, and `Binding/Loader/YamlBindingSpecificationLoader` scans the same type directories for the inline sections independently. Inline bindings depend on the serializer staying lenient about unknown top-level keys; do not add strict top-level key validation here. A reference property's `resolvedBy` key (`Definitions/media/image.yaml` has one) is a separate, simpler mechanism that needs no `bindings:` section at all — see [Binding/README.md](../../Binding/README.md).

## Subdirectories

- **Definitions/** - Core YAML type definitions, organized into category subdirectories
- **Loader/** - Type loading: `AbstractContentSystemElementTypeLoader` (base), `YamlTypeLoader` (filesystem), `DatabaseTypeLoader` (app types in prod), `ElementTypeNameResolver` (path-to-name), `ElementTypeSourceDirectory` (source directory VO), `ResolvedElementTypeSpecificationDto` (loading-to-spec bridge)
- **Registry/** - AbstractContentSystemElementTypeRegistry (decoration pattern contract), ContentSystemElementTypeRegistry (stateless aggregator), CachedContentSystemElementTypeRegistry (cross-request cache decorator)
- **Serialization/** - ElementTypeSpecificationSerializer (YAML ↔ DTO conversion)
- **Specification/** - Value objects (ContentSystemElementTypeSpecification, PropertySpecification, SlotSpecification, CopilotSpecification)
- **Specification/Dto/** - Validation DTOs with Symfony constraint attributes
- **Validation/** - `ElementTypeCollisionDetector` (validates proposed names against registry + inactive app types), `TranslatableType` (translatable requires string), `TypedEnum` (enum type/list/values), `TypedDefault` (default type/value)
