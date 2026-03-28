# Type

Element type system. Declarative type definitions for content elements — what types exist, what properties they have, what slots they provide. Types are defined via YAML files and discovered from core, bundles, plugins, and apps.

## Type Spec as Output Schema

The type specification's `properties` describe what a **hydrated** element looks like in the API response — not what is stored in the database. This is the central design relationship between the type system and the element system.

A type property with a FQCN type (e.g., `SalesChannelProductEntity`) is not stored in the database as a property value. It appears in the element's `properties` map only after hydration, when a data loader or context provider fills it.

A type property with a primitive type (e.g., `string`, `boolean`) is stored in the database as a static property value set at design time.

Both end up in the same `properties` map after hydration. The type spec does not distinguish between them because the API consumer (storefront, admin, headless client) sees a single unified property bag.

### Key-Based Linkage

The property key is the connecting identifier across all systems:

- Type spec: `properties.product` — "this element has a property called `product`"
- Element storage: `data_requirements.product` — "load `product` via this data loader"
- Element storage: `accepts_context.product` — "receive `product` from a parent"
- Hydrator: `$element->setProperty('product', $data)` — "store loaded data under key `product`"
- API output: `properties.product` — serialized SalesChannelProductEntity

The type spec declares WHAT properties exist and their types. The element instance declares HOW each non-primitive property gets its value (via `data_requirements` or `accepts_context`). These are different concerns with different structures, connected by the shared property key.

**Alias and path variations:** The direct key match is the common case. Two exceptions:
- Context consumers may use `propertyAlias` to store received data under a different key than the consumer key (e.g., `accepts_context.product` with `propertyAlias: "item"` stores data under `properties.item`).
- Path-based consumers (e.g., `accepts_context: product.cover`) receive a resolved sub-property from the parent's `product` context, stored under the consumer key or its property alias.

### Type-to-Loader Bridge

`ContentSystemDataLoaderTypeMap` connects type spec FQCNs to data loader capabilities:

- Forward: given a loader source (e.g., `"entity"`), what types can it produce?
- Reverse: given a FQCN (e.g., `SalesChannelProductEntity`), which loaders can produce it?

This bridge is built at compile time by `ContentSystemDataLoaderTypeCompilerPass` and available at runtime via `ContentSystemDataLoaderTypeResolver`. Currently consumed by the Schema API endpoint; designed to also serve future layout validation.

## Architecture

1. **Specification Value Objects** (Specification/) — Immutable VOs: ContentSystemElementTypeSpecification (top-level), PropertySpecification + PropertyType (property with type info), SlotSpecification (slot with allowList/maxElements), CopilotSpecification (LLM metadata). DTOs in Specification/Dto/ carry Symfony validation attributes for input deserialization.

2. **Loading** (Loader/) — Both loaders extend `AbstractContentSystemElementTypeLoader` (shared `load()` contract). `YamlTypeLoader` scans directories for *.yaml files, resolves names via `ElementTypeNameResolver` (path-based), deserializes via `ElementTypeSpecificationSerializer`, validates via Symfony Validator, and deduplicates within the same source. `DatabaseTypeLoader` loads active app types from the `app_content_system_element_type` table (prod only; returns empty in dev where apps load from filesystem via the compiler pass). `ElementTypeSourceDirectory` carries source/path/prefix per directory; `ResolvedElementTypeSpecificationDto` bridges loading and specification creation.

3. **Registry** (Registry/) — Uses the Shopware decoration pattern. `AbstractContentSystemElementTypeRegistry` defines the contract; `ContentSystemElementTypeRegistry` is the stateless aggregator (leaf) that iterates `AbstractContentSystemElementTypeLoader` instances (tagged `content_system.type_loader`); `CachedContentSystemElementTypeRegistry` decorates it with a `cache.system` pool, caching the aggregated result cross-request. `invalidate()` throws `DecorationPatternException` by default — only the cached decorator overrides it.

4. **Compiler Pass** — ContentSystemElementTypeCompilerPass discovers YAML directories from four sources: core Definitions/ directory, bundle metadata, active plugins (customizable via Plugin::getContentTypeDirectory()), and active apps (dev env only, filesystem). Injects directory paths into YamlTypeLoader — no YAML parsing at compile time.

5. **App Integration** — `AppContentSystemElementTypeDefinition` (DAL entity), `ContentSystemElementTypePersister` (syncs YAML to DB with collision detection via `ElementTypeCollisionDetector`), `ContentSystemElementTypeAppValidator` (validates app YAML during manifest validation), `ElementTypeStateService` (toggles `active` column on app activate/deactivate; skips DB writes and cache invalidation when the app has no element types).

## Subdirectories

- **Definitions/** - Core YAML type definitions (49 files: headers, filters, products, content, media, grid)
- **Loader/** - Type loading: `AbstractContentSystemElementTypeLoader` (base), `YamlTypeLoader` (filesystem), `DatabaseTypeLoader` (app types in prod), `ElementTypeNameResolver` (path-to-name), `ElementTypeSourceDirectory` (source directory VO), `ResolvedElementTypeSpecificationDto` (loading-to-spec bridge)
- **Registry/** - AbstractContentSystemElementTypeRegistry (decoration pattern contract), ContentSystemElementTypeRegistry (stateless aggregator), CachedContentSystemElementTypeRegistry (cross-request cache decorator)
- **Serialization/** - ElementTypeSpecificationSerializer (YAML ↔ DTO conversion)
- **Specification/** - Value objects (ContentSystemElementTypeSpecification, PropertySpecification, SlotSpecification, CopilotSpecification)
- **Specification/Dto/** - Validation DTOs with Symfony constraint attributes
- **Validation/** - `ElementTypeCollisionDetector` (validates proposed names against registry + inactive app types), `ValidPropertyConstraints`/`ValidPropertyConstraintsValidator` (type/translatable/enum rules)
