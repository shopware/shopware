## Navigation

- Why a read keeps an unknown option name but throws on a malformed shape: [wire-contract.md](../../../docs/principles/wire-contract.md)
- [docs/option-model.md](docs/option-model.md) - what an option is, its naming and uniqueness rules, and what `default` does
- [docs/option-yaml.md](docs/option-yaml.md) - the declaration file: fields, `breakpointAware`, `kind`
- [docs/write-and-read.md](docs/write-and-read.md) - the strict write, the registry-free read, and where the value surfaces
- [docs/architecture.md](docs/architecture.md) - the seven stages from value object to app integration
- [docs/introspection.md](docs/introspection.md) - the published schema
- [docs/custom-options.md](docs/custom-options.md) - declaring an option from a bundle, plugin or app

## Key Relationship

One declaration, two consumers that cannot drift:
- `StyleOptionConstraintDeriver` turns a `StyleOptionValueType` into the Symfony constraints the write boundary enforces.
- `StyleOptionSpecification::toSchema()` turns the same declaration into the introspection schema.
- Both read the one `AbstractContentSystemStyleOptionRegistry`, so an option that is introspected is enforced and vice versa.

## Constraints

- Option names are flat global wire keys (`col-span`), **not** source-prefixed like element type names
- `Breakpoint` is a fixed framework primitive (`xs, sm, md, lg, xl, xxl`); it is not plugin- or app-extensible. `Breakpoint::values()` is the allowed key set; per-breakpoint values are individually optional
- Value types are limited to the canonical primitives (`string`, `integer`, `number`, `boolean`) — no FQCN, no nested object, no regex
- The constraint `Collection` is derived fresh per write and reused across every element within that write
- Registry uses the Shopware decoration pattern (abstract → leaf → `cache.system` decorator); `invalidate()` throws `DecorationPatternException` unless the decorator overrides it
- The `Plugin` base class is untouched: bundles and plugins share the fixed `Resources/content-system/style-options` convention, so no customization hook exists
- `ElementStyle` immutability is load-bearing: the mutation subsystem aliases an untouched element's style by reference rather than copying it
