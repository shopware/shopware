> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Loaders MUST return `ContentDataLoaderResult` — never throw exceptions
- `load(LoaderInputs $inputs, DataRequirement $requirement, …)` — read every config/property input off `$inputs`; a loader never touches `$requirement->config` nor the `StoredElement`. `$requirement` stays in the signature as the extension-point affordance for a third-party loader keying off `source`
- `LoaderInputResolver` (called by `ElementDataResolver`) dereferences each `PropertyReference` key against the element's stored properties and type-checks it; an absent or wrongly typed stored value resolves to null. Presence and type guards belong there, NOT in the loader — but a domain emptiness fallback (e.g. an empty-string activeId) stays in the loader, because an empty string is a resolved value
- A key's fallback lives in its `ConfigKeySpecification` default, never as a `??` in `load()` — an in-body fallback drifts from the introspection schema
- DI tag: `content_system.data_loader`, indexed by `getRequirementType()` static method
- Each loader needs a config class + serializer pair (config serializer tag: `content_system.config_serializer`) — `ContentSystemDataLoaderCompilerPass` fails the build on a source with no registered serializer (`dataLoaderSourceWithoutConfigSerializer`)
- The same pass rejects two class-level shapes before it reads any specification: a service carrying the `content_system.data_loader` tag that does not extend `AbstractContentDataLoader` (`taggedServiceHasWrongType`), and a tagged class that is abstract (`dataLoaderClassIsAbstract`)
- Domain loaders registered in their owning module's DI, not in `content-system.php`
- Use `$context->getContext()` for entity repository queries
- Built-in sources: `entity`, `entity_collection`, `product_listing`, `navigation`, `service_menu`, `cross_selling`, `product_review`, `product_search`, `product_suggest`, `breadcrumb`, `language`, `currency`, `payment_method`, `shipping_method`
- `@extends AbstractContentDataLoader<T>` PHPDoc annotation required — the base `producibleTypes()`/`resolveProducedType()` derive the produced type from it, and `ContentSystemDataLoaderCompilerPass` dry-runs `extendsDescriptor()` at build time; missing or unresolvable annotation fails the build
- Wildcard loaders (`entity`, `entity_collection`) override `producibleTypes()` and `resolveProducedType()` — enumerate the live `DefinitionInstanceRegistry`, declaring the sales-channel class where a sales-channel definition exists, otherwise the base class
- `configSpecification()` MUST be constructor-independent — `ContentSystemDataLoaderCompilerPass` dry-runs it at build time on an instance built without the constructor (declare it from literals/constants, never from injected state); it fails the build on duplicate keys, value types outside `ConfigKeySpecification::TYPES`, non-string `propertyReference`/`entityName` keys, incoherent defaults (including a default on a required key), a `referencedType` outside `ConfigKeySpecification::REFERENCED_TYPES` or a non-default one (any value but `string`) set on a non-`propertyReference` key, an invalid `mergesInto` (non-`propertyReference` key, a merger not referencing `list<string>`, self-merge, a target that is absent or not a `list<string>` literal, or a target a second merger key already claims — at most one merger per target), the reserved names `loader`/`config` (as source name or config key), and two loaders declaring the same source
