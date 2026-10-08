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
- Loading, registry, compiler pass — [docs/loading-and-apps.md](docs/loading-and-apps.md)
- App bindings and their storage — [docs/app-bindings.md](docs/app-bindings.md)
- Applying a binding — [docs/applying.md](docs/applying.md)
- Write-boundary attribution — [docs/write-boundary.md](docs/write-boundary.md)
- Introspection fold — [docs/introspection.md](docs/introspection.md)
- Authoring guide — [docs/custom-specifications.md](docs/custom-specifications.md)

## Source Code References

- **Value objects** (`Specification/`): `BindingSpecification` (`qualifiedId()`, `isDefault()`, `toSchema()`), `LoaderBinding`, `BindingInput`
- **resolvedBy loader branch**: `ResolvedByLoaderBranch` (module root, `@internal` enum) — the closed tier-A classification; `loaderSource()` returns a loader's `SOURCE` constant, never a bare string literal
- **Default synthesizer**: `DefaultBindingSpecificationSynthesizer` (module root, `@internal`), injected into `YamlBindingSpecificationLoader` — `synthesize()` returns `null` when a file declares no `resolvedBy` property
- **Registry**: `Registry/AbstractContentSystemBindingSpecificationRegistry` — `all()` keyed by `source:id`, `byType()`, `get()`, `invalidate()`; leaf aggregator plus a `cache.system` decorator
- **Loaders**: `Loader/AbstractContentSystemBindingSpecificationLoader` — `YamlBindingSpecificationLoader` (filesystem), `Loader/DatabaseBindingSpecificationLoader` (active app bindings, prod only), `Loader/ResolvedBindingSpecificationDto`
- **Compiler Pass**: `Framework/DependencyInjection/CompilerPass/ContentSystemCompilerPass` — one pass feeding both the type loader and the binding loader. Any new throw there uses `DependencyInjectionException`; that namespace must not throw `ContentSystemException`
- **Serializer**: `Serialization/BindingSpecificationSerializer` — declaration ↔ DTO; `normalize` is what hashes and persists an app binding's schema
- **Canonicalizer**: `Serialization/BindingSpecificationCanonicalizer` — load-time expansion of sugared `resolves` entries; resolves the declared type **overlay-first, then registry**
- **Specification DTO**: `Specification/Dto/BindingSpecificationDto` (carries no id — the loader passes the `bindings:` map key) and `Specification/Dto/BindingSpecificationDtoCollection` (carries the `typeOverlay` the semantic validator reads)
- **Validators**: `Validation/WellFormedBindingSpecification` (structural shape only, no registry lookups) and `Validation/TypeConsistentBindingSpecification` (class-level on the *collection*, violation paths keyed on the binding id)
- **Applicator**: `BindingApplicator` — `apply()` overwrites, `applyFillOnly()` fills only absent keys; both rebuild the element rather than mutating it
- **Mutation op**: `Mutation/Op/BindElement`, plus the fill-apply that `InsertElement` and `ReplaceElement` run — see [docs/applying.md](docs/applying.md)
- **`AttributionReconciler`**: the write seam that re-derives `attributedSpecifications` — see [docs/write-boundary.md](docs/write-boundary.md)
- **App integration**: the DAL entity, persister, lifecycle handler, manifest validator and migration — see [docs/app-bindings.md](docs/app-bindings.md)
- **Diagnostics tie-ins** (fed by stored wiring here): `Resolution/CandidateOrigin::Stored`, `Diagnostics/ViolationCode::MismatchedReferenceType` — see [Resolution](../Resolution/AGENTS.md), [Diagnostics](../Diagnostics/AGENTS.md)

## Constraints

- Uniqueness is by source-qualified id (`"source:id"`), not by bare id, so only a duplicate **within** one source or directory throws `bindingSpecificationDuplicate`
- At most one default specification per element type; `isDefault()` derives `id === type`, computed and never stored. An authored `bindings:` key equal to the file's type name is `bindingSpecificationReservedId` (409), rejected before duplicate detection. At application time zero defaults is a no-op, one is fill-applied, more than one is `bindingSpecificationDefaultAmbiguous` (409)
- `TypeConsistentBindingSpecification` resolves each `resolves` entry's produced type via `Diagnostics/RootContextMapper::resolveType()`; a client-defect exception becomes a validation violation, any other propagates
- An unknown declared type is `bindingSpecificationUnknownType` (400) and non-deterministic sugar is `bindingSpecificationCanonicalizationFailed` (400) — neither is a `CLIENT_DEFECT_CODE`
