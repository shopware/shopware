## Key Relationship

Type spec `properties` = schema for hydrated API output, NOT storage format
- FQCN-typed property → filled by pipeline (data loader or context)
- Primitive-typed property → set statically at design time
- The storage format is published too, on the same endpoint: the `storageSchema` fold derived by `StoredSchemaResolver` (see below). `properties` is not it
- Shared key links: type spec property key = dataRequirements key = acceptsContext key = the key `Rendering/RenderedElementFactory` writes the resolved value under

## Navigation

- Why these constraints hold, and what was not chosen: [type-declarations.md](../../docs/principles/type-declarations.md)
- Why an extension ships declarations, not core edits: [extension-surface.md](../../docs/principles/extension-surface.md)
- Why a declaration defect fails the load, and what was not chosen: [failure-and-loss.md](../../docs/principles/failure-and-loss.md)
- Why the value rules hold, and what was not chosen: [values.md](../../docs/principles/values.md)

## Constraints

- Type names must be unique across all sources (core, bundles, plugins, apps) — duplicates caught at compile time and persist time with source labels: `"core"`, `"bundle:BundleName"`, `"plugin:PluginName"`, `"app:AppName"`
- YAML: one type per file, name is derived from the file path (directory structure + filename → PascalCase colon-separated name) via `ElementTypeNameResolver`. `meta.name` is ignored — the serializer does not read it; names come exclusively from file paths.
- Name prefix is auto-injected: `Sw` for core/bundles, the plugin bundle name (the short `Plugin::getName()` value, not the FQCN) for plugins, app name for apps
- Filenames and directories must be kebab-case: `[a-z0-9]+(-[a-z0-9]+)*`
- Both `.yaml` and `.yml` extensions are accepted
- Registry uses Shopware decoration pattern: `AbstractContentSystemElementTypeRegistry` → `ContentSystemElementTypeRegistry` (leaf) → `CachedContentSystemElementTypeRegistry` (decorator, `cache.system` pool). `invalidate()` throws `DecorationPatternException` by default — only the cached decorator overrides it. Consumers type-hint `AbstractContentSystemElementTypeRegistry`.
- `DatabaseTypeLoader` returns empty in dev (apps load from filesystem via compiler pass in dev)
- Plugin type directory customizable via `Plugin::getContentTypeDirectory()`
- Allow `translatable` only on a property declared `type: string`. Never put the flag on a binding or storage key. Check: `TranslatableTypeValidatorTest` pins the rejections.
- `TypedEnumValidator` enforces: `enum` only on primitives, must be list, values match declared type
- `TypedDefaultValidator` enforces: `default` only on primitives, value matches declared type
- Canonical primitive set: `string`, `integer`, `number`, `boolean`; any other `type` value is treated as a `class-string<Struct>` FQCN (filled by the pipeline). The set is exposed once as `PropertyType::PRIMITIVE_TYPES`, which `PropertyType::isPrimitive()`, `TypedEnumValidator`, `TypedDefaultValidator`, and `Binding/Validation/TypeConsistentBindingSpecificationValidator` all key off, rather than each keeping a private copy. The `enum` / `default` rules use this primitive-vs-FQCN distinction; `translatable` is narrower and does not consult the set at all — it keys off `type === 'string'` (see above)
- `DatabaseTypeLoader` joins `app` and queries `WHERE app.active = 1` (deactivation mechanics in [docs/architecture.md](docs/architecture.md)). `ElementTypeCollisionDetector` also considers types of inactive apps to prevent name collisions across apps. Collision check is best-effort (TOCTOU window); the `UNIQUE KEY` on `app_content_system_element_type.name` is the authoritative guard.
- Declare no context provider or consumer on a type. Check: does the change add a wiring field to `PropertySpecificationDto`? `DefaultBindingSpecificationSynthesizerTest` pins the shorthand.
- Set `required` from this type's own behaviour, never by copying a sibling. Check: can the element render something useful without the value? Then it is optional. `LayoutDiagnosticsTest` pins both outcomes.
- Never read `adminUI` server-side to decide storage, validation or normalization. Declare a storage-relevant kind as its own typed key. Check: does any PHP outside a serializer, a schema generator or a shape check read the block's content?
- Ship an element capability as a declaration the module reads, never as a core edit per entity or per element. Check: could an app ship it with no core edit?
- Fail a declaration or registration defect at registry load or container build, never on a request. Skip and log only a bad database registry row, and give no row a placeholder name. Check: can the defect first surface on a request? `ContentSystemDataLoaderCompilerPassTest` pins the build side.
