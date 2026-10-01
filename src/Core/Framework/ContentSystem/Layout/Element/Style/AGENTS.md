> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Key Relationship

One declaration, two consumers that cannot drift:
- `StyleOptionConstraintDeriver` turns a `StyleOptionValueType` into the Symfony constraints the write boundary enforces.
- `StyleOptionSpecification::toSchema()` turns the same declaration into the introspection schema.
- Both read the one `AbstractContentSystemStyleOptionRegistry`, so an option that is introspected is enforced and vice versa.

## Source Code References

- **Registry**: `Registry/AbstractContentSystemStyleOptionRegistry` — strict `all()`, lenient `allResolved()`, `invalidate()`; leaf `ContentSystemStyleOptionRegistry`, `cache.system` decorator `CachedContentSystemStyleOptionRegistry`
- **Loaders**: `Loader/AbstractContentSystemStyleOptionLoader` — `YamlStyleOptionLoader` (filesystem, all environments), `DatabaseStyleOptionLoader` (active app options, prod only)
- **Compiler Pass**: `Framework/DependencyInjection/CompilerPass/ContentSystemStyleOptionCompilerPass` — discovers the YAML directories and injects them into `YamlStyleOptionLoader`
- **Serializer**: `Serialization/StyleOptionSpecificationSerializer` — declaration ↔ validation DTO
- **Specification**: `Specification/StyleOptionSpecification` and `Specification/StyleOptionValueType`, both immutable; DTOs in `Specification/Dto/`
- **Constraint deriver**: `Validation/StyleOptionConstraintDeriver::derive(StyleOptionValueType): list<Constraint>`
- **Write constraints**: `Layout/Codec/StoredTreeConstraints::styleConstraints()` — the write boundary, reached from `Layout/Field/StoredElementListFieldSerializer::buildConstraints()`
- **Read**: `Layout/Codec/StoredElementCodec::decodeStyle()` — registry-free and structural; every malformed shape throws, nothing is dropped
- **API endpoints**: `Api/Controller/InfoController::getContentSystemStyleOptions()` (`GET /api/_info/content-system-style-options.json`) and the folded `styleOptions` key on `getContentSystemElementTypes()`
- **App integration**: `App/Aggregate/AppContentSystemStyleOption/`, `App/Lifecycle/Persister/ContentSystemStyleOptionPersister`, `App/Lifecycle/Handler/ContentSystemStyleOptionLifecycleHandler`, `App/Validation/ContentSystemStyleOptionAppValidator`
- **Collision detection**: `Validation/StyleOptionCollisionDetector` — proposed names against the registry plus inactive app options; the DB `UNIQUE KEY` is the authoritative guard
- **Value object**: `ElementStyle` — `final readonly`, `toArray()`, `isEmpty()`; `option => (scalar | breakpoint => scalar)`

## Constraints

- Option names are flat global wire keys (`col-span`), **not** source-prefixed like element type names
- `Breakpoint` is a fixed framework primitive (`xs, sm, md, lg, xl, xxl`); it is not plugin- or app-extensible. `Breakpoint::values()` is the allowed key set; per-breakpoint values are individually optional
- Value types are limited to the canonical primitives (`string`, `integer`, `number`, `boolean`) — no FQCN, no nested object, no regex
- The constraint `Collection` is derived fresh per write and reused across every element within that write
- Registry uses the Shopware decoration pattern (abstract → leaf → `cache.system` decorator); `invalidate()` throws `DecorationPatternException` unless the decorator overrides it
- The `Plugin` base class is untouched: bundles and plugins share the fixed `Resources/content-system/style-options` convention, so no customization hook exists
- `ElementStyle` immutability is load-bearing: the mutation subsystem aliases an untouched element's style by reference rather than copying it

## Navigation

- [docs/option-model.md](docs/option-model.md) - what an option is, its naming and uniqueness rules, and what `default` does
- [docs/option-yaml.md](docs/option-yaml.md) - the declaration file: fields, `breakpointAware`, `kind`
- [docs/write-and-read.md](docs/write-and-read.md) - the strict write, the registry-free read, and where the value surfaces
- [docs/architecture.md](docs/architecture.md) - the seven stages from value object to app integration
- [docs/introspection.md](docs/introspection.md) - the published schema
- [docs/custom-options.md](docs/custom-options.md) - declaring an option from a bundle, plugin or app
- [README.md](README.md) - the mental model
