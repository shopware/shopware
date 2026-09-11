> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Key Relationship

Type spec `properties` = schema for hydrated API output, NOT storage format
- FQCN-typed property → filled by pipeline (data loader or context)
- Primitive-typed property → set statically at design time
- The storage format is published too, on the same endpoint: the `storageSchema` fold derived by `StoredSchemaResolver` (see below). `properties` is not it
- Shared key links: type spec property key = dataRequirements key = acceptsContext key = the key `Rendering/RenderedElementFactory` writes the resolved value under

## Navigation

- Symbol index: classes, roles, paths — [docs/symbols.md](docs/symbols.md)
- The five stages a type declaration passes through — [docs/architecture.md](docs/architecture.md)
- The `/api/_info/` type endpoint and the `storageSchema` fold — [docs/introspection.md](docs/introspection.md)
- Why the type spec is the output schema, not the storage format — [docs/output-schema.md](docs/output-schema.md)
- Authoring a custom element type — [docs/custom-types.md](docs/custom-types.md)

## Constraints

- Type names must be unique across all sources (core, bundles, plugins, apps) — duplicates caught at compile time and persist time with source labels: `"core"`, `"bundle:BundleName"`, `"plugin:PluginName"`, `"app:AppName"`
- YAML: one type per file, name is derived from the file path (directory structure + filename → PascalCase colon-separated name) via `ElementTypeNameResolver`. `meta.name` is ignored — the serializer does not read it; names come exclusively from file paths.
- Name prefix is auto-injected: `Sw` for core/bundles, the plugin bundle name (the short `Plugin::getName()` value, not the FQCN) for plugins, app name for apps
- Filenames and directories must be kebab-case: `[a-z0-9]+(-[a-z0-9]+)*`
- Both `.yaml` and `.yml` extensions are accepted
- Registry uses Shopware decoration pattern: `AbstractContentSystemElementTypeRegistry` → `ContentSystemElementTypeRegistry` (leaf) → `CachedContentSystemElementTypeRegistry` (decorator, `cache.system` pool). `invalidate()` throws `DecorationPatternException` by default — only the cached decorator overrides it. Consumers type-hint `AbstractContentSystemElementTypeRegistry`.
- `DatabaseTypeLoader` returns empty in dev (apps load from filesystem via compiler pass in dev)
- Plugin type directory customizable via `Plugin::getContentTypeDirectory()`
- `TranslatableTypeValidator` enforces: `translatable` only on the lone scalar declaration `type: string`. A union is rejected even when `string` is its single member — `type: [string]` with the flag is a declaration error, not a skipped property, because the stored language map is defined for one string type and no other
- `TypedEnumValidator` enforces: `enum` only on primitives, must be list, values match declared type
- `TypedDefaultValidator` enforces: `default` only on primitives, value matches declared type
- Canonical primitive set: `string`, `integer`, `number`, `boolean`; any other `type` value is treated as a `class-string<Struct>` FQCN (filled by the pipeline). The set is exposed once as `PropertyType::PRIMITIVE_TYPES`, which `PropertyType::isPrimitive()`, `TypedEnumValidator`, `TypedDefaultValidator`, and `Binding/Validation/TypeConsistentBindingSpecificationValidator` all key off, rather than each keeping a private copy. The `enum` / `default` rules use this primitive-vs-FQCN distinction; `translatable` is narrower and does not consult the set at all — it keys off the scalar identity `type === 'string'`, so no union passes (see above)
- `DatabaseTypeLoader` joins `app` and queries `WHERE app.active = 1` (deactivation mechanics in [docs/architecture.md](docs/architecture.md)). `ElementTypeCollisionDetector` also considers types of inactive apps to prevent name collisions across apps. Collision check is best-effort (TOCTOU window); the `UNIQUE KEY` on `app_content_system_element_type.name` is the authoritative guard. A persisted row whose schema fails to decode or validate is skipped and logged at `warning` level rather than failing the whole load — unlike `YamlTypeLoader`, which fails hard on an authored file.
