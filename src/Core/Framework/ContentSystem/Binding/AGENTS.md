> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Navigation

- Specification model — [docs/specification-model.md](docs/specification-model.md)
- Authoring tiers — [docs/authoring-sugar.md](docs/authoring-sugar.md)
- Entity-name derivation — [docs/entity-name-derivation.md](docs/entity-name-derivation.md)
- `resolvedBy` shorthand — [docs/resolved-by.md](docs/resolved-by.md)
- Inline `bindings:`, app overlay — [docs/inline-bindings.md](docs/inline-bindings.md)
- The per-type default — [docs/default-specification.md](docs/default-specification.md)
- Load-time validation — [docs/validation.md](docs/validation.md)
- Loading, registry, apps — [docs/loading-and-apps.md](docs/loading-and-apps.md)
- Applying a binding — [docs/applying.md](docs/applying.md)
- Write-boundary attribution — [docs/write-boundary.md](docs/write-boundary.md)
- Introspection fold — [docs/introspection.md](docs/introspection.md)
- Authoring guide — [docs/custom-specifications.md](docs/custom-specifications.md)
- Symbol index: classes, roles, paths — [docs/symbols.md](docs/symbols.md)

## Constraints

- Uniqueness is by source-qualified id (`"source:id"`), not by bare id: `ContentSystemBindingSpecificationRegistry::all()` keys on `BindingSpecification::qualifiedId()`, so only a duplicate **within** one source/directory throws `bindingSpecificationDuplicate`
- **App type overlay:** the app persister and validator pass a type overlay built from the app's own `Resources/content-system/types`; every non-app path passes an empty overlay
- **Default uniqueness** is at most one default specification per element type (`isDefault()` derives `id === type`, computed, never stored). An authored `bindings:` key equal to the file's type name is rejected before duplicate detection (`bindingSpecificationReservedId`, 409). At *application* time the default set is read zero/one/more: no-op / fill-applied / `bindingSpecificationDefaultAmbiguous` (409)
- `TypeConsistentBindingSpecification` resolves each `resolves` entry's produced type via `Diagnostics/RootContextMapper::resolveType()`; a client-defect exception becomes a validation violation, any other propagates
- A DECLARATION stays scalar: `WellFormedBindingSpecification` admits a scalar or `null` `inputs` default and nothing else, and no author writes a language id. The language shape is applied at seed time — `BindingApplicator` seeds a translatable property's `inputs` default under `Defaults::LANGUAGE_SYSTEM`, through the same shape rule `Layout/Type/Specification/PropertyType::storedDefault()` states for a type default. Shared rule, separate producers: one default is declared on the specification, the other on the type
- `TypeConsistentBindingSpecification` keeps its `isPrimitive()` gate, so a translatable property stays a valid `inputs` target, and additionally rejects a `null` default on one (`inputsEntryNullDefaultOnTranslatableMessage`): `null` is no valid language-map entry, so it could never seed
- Loaders are tagged `content_system.binding_specification_loader`. Core ships no dedicated binding-specification directory and no authored inline `bindings:` entry: every core binding specification is a synthesized default, seven in all, each from the `resolvedBy` properties of one file under `Layout/Type/Definitions/`. Inventory: [docs/default-specification.md](docs/default-specification.md)
- `DatabaseBindingSpecificationLoader` validates each row independently (identified by `source:id`); a row whose schema fails to decode or validate is skipped and logged at `warning` level rather than failing the whole load — unlike `YamlBindingSpecificationLoader`, which fails hard on an authored file
