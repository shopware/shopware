> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Key Relationship

Type spec `properties` = schema for hydrated API output, NOT storage format
- FQCN-typed property → filled by pipeline (data loader or context)
- Primitive-typed property → set statically at design time
- The storage format is published too, on the same endpoint: the `storageSchema` fold derived by `StoredSchemaResolver` (see below). `properties` is not it
- Shared key links: type spec property key = dataRequirements key = acceptsContext key = the key `Rendering/RenderedElementFactory` writes the resolved value under

## Source Code References

- **Registry**: `Registry/AbstractContentSystemElementTypeRegistry` (abstract, decoration pattern), `Registry/ContentSystemElementTypeRegistry` (stateless aggregator), `Registry/CachedContentSystemElementTypeRegistry` (`cache.system` pool decorator)
- **Compiler Pass**: `Framework/DependencyInjection/CompilerPass/ContentSystemCompilerPass` (discovers from core, bundles, plugins, apps; injects the type-directory set into both `YamlTypeLoader` and the binding system's `YamlBindingSpecificationLoader`, and the parallel `Resources/content-system/presets` directory set into `Layout/Preset/Loader/YamlLayoutPresetLoader`)
- **Loaders**: `Loader/AbstractContentSystemElementTypeLoader` (base contract), `Loader/YamlTypeLoader` (filesystem; also exposes `loadOverlayFromDirectory(directory, source, prefix): array<string, ContentSystemElementTypeSpecification>`, a registry-independent single-directory load keyed by resolved type name, see [Binding/docs/inline-bindings.md](../../Binding/docs/inline-bindings.md)), `Loader/DatabaseTypeLoader` (app types, prod only), `Loader/ElementTypeNameResolver` (path → name)
- **Serializer**: `Serialization/ElementTypeSpecificationSerializer` (YAML ↔ DTO)
- **API Endpoint**: `Api/Controller/InfoController::getContentSystemElementTypes()` (`GET /api/_info/content-system-element-types.json`)
- **Storage schema**: `StoredSchemaResolver::resolve(ContentSystemElementTypeSpecification): array<string, array{kind, type, required, default?}>` — the per-type `storageSchema` fold on that endpoint, keyed by STORED key. See [docs/introspection.md#storageschema](docs/introspection.md#storageschema) for the three kinds, their precedence, and the typing rules
- **App Integration**: `App/Lifecycle/Persister/ContentSystemElementTypePersister`, `App/Validation/ContentSystemElementTypeAppValidator`, `App/Lifecycle/Handler/ContentSystemElementTypeLifecycleHandler` (persists app types on install/update)
- **Collision Detection**: `Validation/ElementTypeCollisionDetector` (validates proposed names against registry + inactive app types)
- **Type Map Bridge**: `Schema/ContentSystemDataLoaderMap` — connects FQCNs to loader sources
- **Stored defaults**: `StoredDefaultProvider::forType(AbstractContentSystemElementTypeRegistry $registry, string $type): array<string, string|int|float|bool|array<string, mixed>>`. The single per-type rule: a primitive property contributes its non-null `default()`, while a property with nested members contributes an object containing recursively collected member defaults; a property with neither contributes nothing. The one definition of a type's stored defaults, consumed by `Mutation/AbstractLayoutMutation` (scaffold + replace seeding) and `Layout/LayoutDefaultSeeder` (write-boundary seeding). The caller guarantees the type is registered
- **Specification accessors**: `Specification/ContentSystemElementTypeSpecification` exposes `name(): string` (the type's unique identifier — the key consumers match/look up by), `source(): string` (the source-label prefix, e.g. `core` / `plugin:Name`, default `''`), `properties(): array<string, PropertySpecification>` (the declared property map the `Resolution/` kernel — `Resolution/ElementResolver`, `Resolution/AvailableContextResolver` — iterates to resolve each property, reading each `PropertySpecification` and its `PropertyType::isPrimitive()`), `slots(): list<SlotSpecification>` (the declared slots; each `SlotSpecification` exposes `name(): string`, consumed by `Mutation/Op/ReplaceElement` to decide which slot children a new type can keep), and `toSchema(): array` (serializes the spec to the `ElementTypeSchema` wire shape served by `GET /api/_info/content-system-element-types.json`)

## Constraints

- Type names must be unique across all sources (core, bundles, plugins, apps) — duplicates caught at compile time and persist time with source labels: `"core"`, `"bundle:BundleName"`, `"plugin:PluginName"`, `"app:AppName"`
- YAML: one type per file, name is derived from the file path (directory structure + filename → PascalCase colon-separated name) via `ElementTypeNameResolver`. `meta.name` is ignored — the serializer does not read it; names come exclusively from file paths.
- Name prefix is auto-injected: `Sw` for core/bundles, the plugin bundle name (the short `Plugin::getName()` value, not the FQCN) for plugins, app name for apps
- Filenames and directories must be kebab-case: `[a-z0-9]+(-[a-z0-9]+)*`
- Both `.yaml` and `.yml` extensions are accepted
- Registry uses Shopware decoration pattern: `AbstractContentSystemElementTypeRegistry` → `ContentSystemElementTypeRegistry` (leaf) → `CachedContentSystemElementTypeRegistry` (decorator, `cache.system` pool). `invalidate()` throws `DecorationPatternException` by default — only the cached decorator overrides it. Consumers type-hint `AbstractContentSystemElementTypeRegistry`.
- `DatabaseTypeLoader` returns empty in dev (apps load from filesystem via compiler pass in dev)
- Plugin type directory customizable via `Plugin::getContentTypeDirectory()`
- `TranslatableTypeValidator` enforces: `translatable` only on `string` type
- `TypedEnumValidator` enforces: `enum` only on primitives, must be list, values match declared type
- `TypedDefaultValidator` enforces: `default` only on primitives, value matches declared type
- Canonical primitive set: `string`, `integer`, `number`, `boolean`; any other `type` value is treated as a `class-string<Struct>` FQCN (filled by the pipeline). The set is exposed once as `PropertyType::PRIMITIVE_TYPES`, which `PropertyType::isPrimitive()`, `TypedEnumValidator`, `TypedDefaultValidator`, and `Binding/Validation/TypeConsistentBindingSpecificationValidator` all key off, rather than each keeping a private copy. The `enum` / `default` rules use this primitive-vs-FQCN distinction; `translatable` is narrower — it keys off `type === 'string'` only (see above)
- Whether a *stored value* conforms to a declared type is `PropertyType`'s alone (the schema-literal rules `TypedEnumValidator` / `TypedDefaultValidator` still keep their own table): `enforceableTypes()` answers which primitives may be enforced (`null` for `object`, an FQCN, or a union carrying either) and `admits()` judges a value against them, treating `null` as admissible under every primitive. `Layout/Codec/PropertyTypeConformanceValidator`, the `MismatchedPropertyType` arm of `Diagnostics/LayoutDiagnostics`, and `Mutation/Op/ReplaceElement`'s property carry-over all delegate here rather than keeping their own copy
- `DatabaseTypeLoader` joins `app` and queries `WHERE app.active = 1` (deactivation mechanics in [docs/architecture.md](docs/architecture.md)). `ElementTypeCollisionDetector` also considers types of inactive apps to prevent name collisions across apps. Collision check is best-effort (TOCTOU window); the `UNIQUE KEY` on `app_content_system_element_type.name` is the authoritative guard. It deserializes all active-app rows and validates them together; a missing name or a schema that fails to decode, deserialize, or validate aborts the whole load, matching `YamlTypeLoader`'s fail-fast behavior, while validator infrastructure failures propagate unchanged.
