# Binding

The binding specification system. A binding specification is an authored declaration wiring one element type's reference properties to data loaders and seeding its primitive properties, so an editor (or an agentic layout builder) can apply a complete, pre-validated data wiring to an element in one action instead of hand-assembling loader configs. It mirrors the universal style option system (`Layout/Element/Style/`) one directory at a time, varying only the subject: a specification is a declarative artifact, discovered from core, bundles, plugins, and apps, aggregated by one cache-decorated registry that both validation and introspection read.

## Guides

- [docs/specification-model.md](docs/specification-model.md) - What a specification declares, what "binding" is not, and why the shape is repeated rather than shared.
- [docs/default-specification.md](docs/default-specification.md) - The at-most-one default per type, how it is derived, and when it is auto-applied.
- [docs/authoring-sugar.md](docs/authoring-sugar.md) - The three `resolves` authoring tiers and their load-time canonicalization.
- [docs/entity-name-derivation.md](docs/entity-name-derivation.md) - Why FQCN-to-entity-name derivation can turn ambiguous after an install, and when that surfaces.
- [docs/validation.md](docs/validation.md) - The structural and semantic constraints a declaration passes at load time.
- [docs/loading-and-apps.md](docs/loading-and-apps.md) - Loaders, the decorated registry, the compiler pass, and the app lifecycle.
- [docs/resolved-by.md](docs/resolved-by.md) - The `resolvedBy` shorthand that needs no authored `bindings:` entry.
- [docs/inline-bindings.md](docs/inline-bindings.md) - Authoring a specification inline in its element-type file, including the app type overlay.
- [docs/applying.md](docs/applying.md) - The two applicator modes and the three mutation operations that drive them.
- [docs/write-boundary.md](docs/write-boundary.md) - Attribution reconciliation at the DAL write seam, and the diagnostics tie-ins.
- [docs/introspection.md](docs/introspection.md) - The `bindingSpecifications` fold clients read to discover applicable specifications.
- [docs/custom-specifications.md](docs/custom-specifications.md) - The plugin- and app-facing authoring guide.

## Subdirectories

- **(module root)** - `BindingApplicator` (the merge that applies a specification's wiring — two modes, overwrite and fill-only, see [docs/applying.md](docs/applying.md)), `AttributionReconciler` (write-boundary attribution honesty, see [docs/write-boundary.md](docs/write-boundary.md)), `ResolvedByLoaderBranch` (the closed tier-A classification: `Entity` → the `entity` loader, `EntityCollection` → the `entity_collection` loader), `DefaultBindingSpecificationSynthesizer` (turns a type's `resolvedBy` properties into its synthesized default specification, see [docs/resolved-by.md](docs/resolved-by.md))
- **Loader/** - `AbstractContentSystemBindingSpecificationLoader` (base), `YamlBindingSpecificationLoader` (filesystem), `DatabaseBindingSpecificationLoader` (app bindings in prod), `ResolvedBindingSpecificationDto` (loading-to-specification bridge). The source-directory VO is the shared `Layout/Type/Loader/ElementTypeSourceDirectory` (source/path/prefix)
- **Registry/** - `AbstractContentSystemBindingSpecificationRegistry` (decoration pattern contract), `ContentSystemBindingSpecificationRegistry` (stateless aggregator), `CachedContentSystemBindingSpecificationRegistry` (cross-request cache decorator)
- **Serialization/** - `BindingSpecificationSerializer` (YAML/JSON-schema ↔ DTO), `BindingSpecificationCanonicalizer` (load-time sugar expansion, registry-driven)
- **Specification/** - Value objects: `BindingSpecification` (`id`, `type`, `label`, `resolves`, `inputs`, `source`; `toSchema()` for introspection), `LoaderBinding` (`source`, `config`), `BindingInput` (`hasDefault`, `default`)
- **Specification/Dto/** - `BindingSpecificationDto` (deserialization + load-time validation shape, carries both class-level constraints) and its collection
- **Validation/** - `WellFormedBindingSpecification` (+ validator, structural shape), `TypeConsistentBindingSpecification` (+ validator, live-registry semantics)
