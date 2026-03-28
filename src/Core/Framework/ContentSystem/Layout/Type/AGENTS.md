@README.md

## Key Relationship

Type spec `properties` = schema for hydrated API output, NOT storage format
- FQCN-typed property → filled by pipeline (data loader or context)
- Primitive-typed property → set statically at design time
- Shared key links: type spec property key = data_requirements key = accepts_context key = setProperty() key

## Source Code References

- **Registry**: `Registry/AbstractContentSystemElementTypeRegistry` (abstract, decoration pattern), `Registry/ContentSystemElementTypeRegistry` (stateless aggregator), `Registry/CachedContentSystemElementTypeRegistry` (`cache.system` pool decorator)
- **Compiler Pass**: `DependencyInjection/CompilerPass/ContentSystemElementTypeCompilerPass` (discovers from core, bundles, plugins, apps)
- **Loaders**: `Loader/AbstractContentSystemElementTypeLoader` (base contract), `Loader/YamlTypeLoader` (filesystem), `Loader/DatabaseTypeLoader` (app types, prod only), `Loader/ElementTypeNameResolver` (path → name)
- **Serializer**: `Serialization/ElementTypeSpecificationSerializer` (YAML ↔ DTO)
- **API Endpoint**: `Api/Controller/InfoController::getContentSystemElementTypes()` (`GET /api/_info/content-system-element-types.json`)
- **App Integration**: `App/Lifecycle/Persister/ContentSystemElementTypePersister`, `App/Validation/ContentSystemElementTypeAppValidator`, `App/ContentSystem/ElementTypeStateService` (activate/deactivate lifecycle)
- **Collision Detection**: `Validation/ElementTypeCollisionDetector` (validates proposed names against registry + inactive app types)
- **Type Map Bridge**: `Schema/ContentSystemDataLoaderTypeMap` — connects FQCNs to loader sources

## Constraints

- Type names must be unique across all sources (core, bundles, plugins, apps) — duplicates caught at compile time and persist time with source labels: `"core"`, `"bundle:BundleName"`, `"plugin:PluginName"`, `"app:AppName"`
- YAML: one type per file, name is derived from the file path (directory structure + filename → PascalCase colon-separated name) via `ElementTypeNameResolver`. `meta.name` is ignored — the serializer does not read it; names come exclusively from file paths.
- Name prefix is auto-injected: `Sw` for core/bundles, bundle class name for plugins, app name for apps
- Filenames and directories must be kebab-case: `[a-z0-9]+(-[a-z0-9]+)*`
- Both `.yaml` and `.yml` extensions are accepted
- Registry uses Shopware decoration pattern: `AbstractContentSystemElementTypeRegistry` → `ContentSystemElementTypeRegistry` (leaf) → `CachedContentSystemElementTypeRegistry` (decorator, `cache.system` pool). `invalidate()` throws `DecorationPatternException` by default — only the cached decorator overrides it. Consumers type-hint `AbstractContentSystemElementTypeRegistry`.
- `DatabaseTypeLoader` returns empty in dev (apps load from filesystem via compiler pass in dev)
- Plugin type directory customizable via `Plugin::getContentTypeDirectory()`
- `ValidPropertyConstraintsValidator` enforces: `translatable` only on `string`, `enum` only on primitives
- `DatabaseTypeLoader` queries `WHERE active = 1` — deactivated app types excluded from registry. `ElementTypeCollisionDetector` also considers inactive types to prevent name collisions across apps.
