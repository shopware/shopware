## Constraints

- Derive the write constraints and the introspection schema from the one declaration through the one registry. Check: does a validation or introspection path read option data from anywhere but `StyleOptionSpecification` via `AbstractContentSystemStyleOptionRegistry`? [write-and-read.md](docs/write-and-read.md#strict-write-registry-free-read)
- Keep option names flat, global and unique: the name is the kebab-case filename and the wire key, never source-prefixed. Check: does the change prefix a name, or admit a duplicate name from any source? [custom-options.md](docs/custom-options.md#name-resolution)
- Keep style value types to `string`, `integer`, `number` and `boolean`, every string or number bounded. Check: does the change add an FQCN, nested or regex value type, or lift the `DEFAULT_STRING_MAX_LENGTH` bound? [option-model.md](docs/option-model.md#the-option-model)
- Keep the style write strict over what `ElementStyleNormalizer` leaves, and the read registry-free and structural. Check: does `StoredElementCodec::decodeStyle()` consult the registry, or does the write admit an unknown option, an unknown breakpoint or a flat option sent as a breakpoint map? [wire-contract.md](../../../docs/principles/wire-contract.md)
- Keep `default` an authoring hint: it reaches stored JSON only through `ElementStyleNormalizer` at the write boundary, and `style` stays omitted when empty. Check: does a serve, diagnostics or output path apply a default or emit an empty `style`? [stored-model.md](../../../docs/principles/stored-model.md)
- Derive the style constraint `Collection` fresh per write, never memoized across writes. Check: does the change cache the `StoredTreeConstraints` output beyond one write? [write-and-read.md](docs/write-and-read.md#strict-write-registry-free-read)
- Add no `Plugin::getStyleOptionDirectory()` hook: bundles and plugins share the fixed `Resources/content-system/style-options` directory. Check: does the change touch the `Plugin` base class? [custom-options.md](docs/custom-options.md#registration)
- Keep `ElementStyle` immutable: the mutation subsystem aliases an untouched element's `ElementStyle` by reference into rebuilt and cloned nodes. Check: does the change add a setter or an in-place edit? [option-model.md](docs/option-model.md#the-option-model)

## Where to look

- The declaration file format, `kind`, and the breakpoint key set and cascade: [option-yaml.md](docs/option-yaml.md#breakpoints)
- Universal-not-per-type, `breakpointAware`, `Breakpoint`, `default` and the value objects of a declaration: [option-model.md](docs/option-model.md)
- The strict write, the registry-free read, unknown-option reporting and the output formats: [write-and-read.md](docs/write-and-read.md)
- Loaders, registry decoration, `all()` versus `allResolved()`, collision precedence and app integration: [architecture.md](docs/architecture.md)
- Registering an option from a plugin or app, name resolution, collision detection and the app lifecycle: [custom-options.md](docs/custom-options.md)
- The two read surfaces one declaration feeds and the Admin API endpoint: [introspection.md](docs/introspection.md)
- The loader and serializer surfaces the app persister reuses: [README.md](README.md#shared-surfaces)
