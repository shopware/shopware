## Constraints

- Allow `translatable` only on a property declared `type: string`. Never put the flag on a binding or storage key. Check: does the change set `translatable` on a non-string property, a binding or a storage key? [custom-types.md](docs/custom-types.md#yaml-structure)
- Declare no context provider or consumer on a type. Check: does the change add a wiring field to `PropertySpecificationDto`? `DefaultBindingSpecificationSynthesizerTest` pins the shorthand. [type-declarations.md](../../docs/principles/type-declarations.md)
- Set `required` from this type's own behaviour, never by copying a sibling. Check: can the element render something useful without the value? Then it is optional. `LayoutDiagnosticsTest` pins both outcomes. [type-declarations.md](../../docs/principles/type-declarations.md)
- Never read `adminUI` server-side to decide storage, validation or normalization. Declare a storage-relevant kind as its own typed key. Check: does any PHP outside a serializer, a schema generator or a shape check read the block's content? [type-declarations.md](../../docs/principles/type-declarations.md)
- Ship an element capability as a declaration the module reads, never as a core edit per entity or per element. Check: could an app ship it with no core edit? [extension-surface.md](../../docs/principles/extension-surface.md)
- Fail a declaration or registration defect at registry load or container build, never on a request. Skip and log only a bad database registry row, and give no row a placeholder name. Check: can the defect first appear on a request? `ContentSystemDataLoaderCompilerPassTest` pins the build side. [failure-and-loss.md](../../docs/principles/failure-and-loss.md)

## Where to look

- Type properties as the hydrated output schema, and the property key shared by type spec, `dataRequirements`, `acceptsContext` and the rendered element: [output-schema.md](docs/output-schema.md#key-based-linkage)
- The `storageSchema` fold and `StoredSchemaResolver`: [introspection.md](docs/introspection.md#storageschema)
- Loaders, registry decoration, compiler pass, `DatabaseTypeLoader` and app integration: [architecture.md](docs/architecture.md)
- Naming alignment with Storefront Twig components: [architecture.md](docs/architecture.md#naming-alignment-with-storefront-twig-components)
- Registering a type from a plugin or app, and the plugin type directory hook: [custom-types.md](docs/custom-types.md#registration)
- Type name derivation from the file path, the source prefixes and the kebab-case filename rule: [custom-types.md](docs/custom-types.md#name-resolution)
- Type YAML, property fields, the canonical primitive set and the `enum` and `default` rules: [custom-types.md](docs/custom-types.md#yaml-structure)
- Name uniqueness across sources, collision detection and the source labels: [custom-types.md](docs/custom-types.md#collision-detection), [introspection.md](docs/introspection.md)
- App activation and the cached registry: [custom-types.md](docs/custom-types.md#app-lifecycle)
- Inline `bindings:` sections in a type YAML: [README.md](README.md#inline-bindings-sections)
- Why the value rules hold, and what was not chosen: [values.md](../../docs/principles/values.md)
