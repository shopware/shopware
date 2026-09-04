# Style

Universal style option system. A defined set of presentation attributes (alignment, span, spacing, display) settable on **every** content element regardless of its type, served through the Store API and extensible by plugins and apps. Each option is per-breakpoint by default; an option opts out with `breakpointAware: false` to take a single flat scalar value instead. It mirrors the element type system (`Layout/Type/`) one directory at a time, varying only the subject: an option is a declarative specification, discovered from core, bundles, plugins, and apps, aggregated by one cache-decorated registry that both validation and introspection read.

## Guides

- [docs/option-model.md](docs/option-model.md) - Why an option is universal rather than per-type, and the value objects a declaration is made of.
- [docs/write-and-read.md](docs/write-and-read.md) - The strict registry-backed write, the registry-free read, and where the value surfaces.
- [docs/architecture.md](docs/architecture.md) - Value objects, loaders, registry, serialization, validation, compiler pass, and app integration.
- [docs/introspection.md](docs/introspection.md) - The two read surfaces one declaration feeds, and the Admin API endpoint.
- [docs/custom-options.md](docs/custom-options.md) - The plugin- and app-facing authoring guide.
- [docs/option-yaml.md](docs/option-yaml.md) - The flat declaration file format and the breakpoint key set.

## Subdirectories

- **Definitions/** - Core YAML option definitions (5 files): `display` (boolean), `align-self` / `justify-self` (string enum), `col-span` / `row-span` (integer range)
- **Loader/** - Option loading: `AbstractContentSystemStyleOptionLoader` (base), `YamlStyleOptionLoader` (filesystem), `DatabaseStyleOptionLoader` (app options in prod), `StyleOptionSourceDirectory` (source directory VO), `ResolvedStyleOptionSpecificationDto` (loading-to-spec bridge)
- **Registry/** - `AbstractContentSystemStyleOptionRegistry` (decoration pattern contract), `ContentSystemStyleOptionRegistry` (stateless aggregator), `CachedContentSystemStyleOptionRegistry` (cross-request cache decorator)
- **Serialization/** - `StyleOptionSpecificationSerializer` (YAML/array ↔ DTO)
- **Specification/** - Value objects (`StyleOptionSpecification`, `StyleOptionValueType`)
- **Specification/Dto/** - Validation DTOs with Symfony constraint attributes
- **Validation/** - `StyleOptionConstraintDeriver` (declaration → constraints), `StyleOptionCollisionDetector` (unique-name guard), `TypedStyleOption` (+ validator, declaration consistency)
