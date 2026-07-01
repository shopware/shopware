# Binding

The binding specification system. A binding specification is an authored declaration wiring one element type's reference properties to data loaders and seeding its primitive properties, so an editor (or an agentic layout builder) can apply a complete, pre-validated data wiring to an element in one action instead of hand-assembling loader configs. It mirrors the universal style option system (`Layout/Element/Style/`) one directory at a time, varying only the subject: a specification is a declarative artifact, discovered from core, bundles, plugins, and apps, aggregated by one cache-decorated registry that both validation and introspection read.

## Not a Root Source

"Binding" names a different relationship than "root source" (`Adapter/RootSourceRegistry`): a root source is the registered origin of a layout's root-ambient context (an entity type, a section, or "none"); a binding is the relationship between one reference property and the source that fills it — the sense `Diagnostics/ViolationScope::Binding` already carries. A `BindingSpecification` authors such a binding for one element type; it says nothing about what a layout's root is bound to. See `../NAMING.md`.

## The Specification Model

- `BindingSpecification` — the immutable declared contract of one binding: its `id`, the element `type` it applies to, a human `label`, a `resolves` map (reference property key → `LoaderBinding`), and an `inputs` map (primitive property key → `BindingInput`). `toSchema()` serializes it for introspection.
- `LoaderBinding` — one `resolves` entry: a data loader `source` plus its `config`. Becomes a `Layout/Element/DataRequirement/DataRequirement` when applied to an element.
- `BindingInput` — one `inputs` entry: an optional typed default for a primitive property, with presence modeled explicitly (`hasDefault()`) so "no default" is distinct from "default is null".

A specification's `resolves`/`inputs` keys are validated at load time against the declared type's actual properties (§6, below), so an applied specification can never target a property the type does not have.

## Two Validators, Two Concerns

Every declaration passes two class-level constraints in order:

- **`Validation/WellFormedBindingSpecification`** — structural shape only: `type`/`label` non-blank, `resolves`/`inputs` (if present) are arrays of arrays, each `resolves` entry declares a non-blank `loader`, each `inputs` entry's `default` (if present) is a scalar or `null`. No registry lookups.
- **`Validation/TypeConsistentBindingSpecification`** (§6) — semantic consistency against live registries: the declared `type` is registered; every `resolves` key names a reference property the type actually has, and the configured loader's produced type is assignable to that property's declared FQCN; an `entity` loader's `property` config names a primitive property; every `inputs` key names a primitive property, and a present `default` matches that property's declared type.

Splitting the two mirrors the element-type and style-option systems' "shape first, then live-registry consistency" load-time gate, so a malformed declaration and a declaration that targets a real but wrong property fail with distinctly diagnosable violations.

## Loading, Registry, App Integration

Three loading tiers, one registry, discovered by one compiler pass — the same shape as `Layout/Element/Style/`:

1. **Loading** (`Loader/`) — both loaders extend `AbstractContentSystemBindingSpecificationLoader`. `YamlBindingSpecificationLoader` handles core, bundle, and plugin bindings in every environment plus app bindings in dev; it deserializes each YAML file via `Serialization/BindingSpecificationSerializer`, validates the DTOs, and deduplicates within and across directories (`ContentSystemException::bindingSpecificationDuplicate`). `DatabaseBindingSpecificationLoader` loads active app bindings from `app_content_system_binding_specification` in prod and returns empty in dev. `BindingSpecificationSourceDirectory` carries source, path, and prefix per directory; `ResolvedBindingSpecificationDto` bridges loading and specification creation.
2. **Registry** (`Registry/`) — the Shopware decoration pattern. `AbstractContentSystemBindingSpecificationRegistry` defines the contract: `all()` (keyed by source-qualified id, `source:id`), `byType(type)`, `get(qualifiedId)`, and `invalidate()`. `ContentSystemBindingSpecificationRegistry` is the stateless aggregator (leaf) over loaders tagged `content_system.binding_specification_loader`; `CachedContentSystemBindingSpecificationRegistry` decorates it with a `cache.system` pool.
3. **App Integration** — `App/Aggregate/AppContentSystemBindingSpecification/` (DAL entity, table `app_content_system_binding_specification`, `UNIQUE (app_id, name)` — bindings are unique only within their app, unlike the globally unique style options, so two apps may legitimately ship the same bare id), `App/Lifecycle/Persister/ContentSystemBindingSpecificationPersister` (hash-based upsert/delete in one transaction, no cross-source collision check since the DB unique key is per-app), `App/Lifecycle/Handler/ContentSystemBindingSpecificationLifecycleHandler` (persists on install/update; invalidates the registry on activate/deactivate/uninstall/delete), and `App/Validation/ContentSystemBindingSpecificationAppValidator` (manifest-time schema validation only — collisions are a DB-unique-key concern, not a manifest one).
4. **Compiler Pass** — `Framework/DependencyInjection/CompilerPass/ContentSystemBindingSpecificationCompilerPass` discovers YAML directories from core `Definitions/`, each bundle's and each active plugin's fixed `Resources/content-system/binding-specifications`, and (dev only) each active app's directory, then injects them into `YamlBindingSpecificationLoader`.

## Applying a Binding

`Mutation/Op/BindElement` (the ninth `Mutation/Op`) applies one specification's wiring onto one element, through the same draft/persisted mutation machinery as every other structural edit:

- Each `resolves` entry becomes a concrete `DataRequirement`, merged into the element's existing data requirements and **overwriting** the same keys — re-applying a binding over an already-bound key replaces its wiring.
- Each `inputs` entry with a default seeds that primitive property, but only when the element does not already carry the key (`ContentElement::hasProperty()`, not a null check, so an authored explicit `null` is never overwritten).
- Every wired key's attribution is recorded into the element's `attributedSpecifications` map (also merged, overwriting), so a client can later ask "which specification wired this key".

Exposed by two admin routes mirroring every other op: the draft `POST /api/_action/content-system/layout/bind-element` and the persisted `POST /api/_action/content-system/layout/{layoutId}/bind-element`. An unregistered `bindingSpecificationId` (`bindingSpecificationNotFound`) or a specification whose declared `type` does not match the target element's `component` (`bindingTypeMismatch`) is rejected with `400`.

## Attribution Honesty at the Write Boundary

`AttributionReconciler` re-derives every element's `attributedSpecifications` at the single DAL write chokepoint for the `layout` field (the same seam `Layout/LayoutDefaultSeeder` occupies), so a persisted attribution is honest by construction: an entry survives a write only while the element's current wiring for that key still equals what the attributed specification's binding for that key produces (compared via the canonicalized encoded config). A key whose wiring has since diverged — or whose specification or binding no longer exists — is silently dropped, never flagged as an error; a user who hand-edits a key's wiring away from the specification simply loses that key's attribution, and every other key keeps its own independently. This is a drop-not-throw seam by design: attribution is bookkeeping, not a constraint the write should fail over.

## Response-Layer Introspection

Two response-assembly-layer concerns, deliberately kept out of the resolution/diagnostics kernel:

- `ApplicableBindingsResolver` computes, for every element in a tree, the specifications applicable to its type — a per-`component` lookup against the registry, not a resolution against the element's actual wiring or ancestry (a specification declared for a type is always applicable at any position of that type). Feeds the `applicableBindings` field on the diagnose and mutation responses. Never consulted by `Diagnostics/LayoutDiagnostics::analyze()` or the `content_layout` write gate.
- `GET /api/_info/content-system-binding-specifications.json` (`InfoController::getContentSystemBindingSpecifications()`) serves the full registered catalog keyed by source-qualified id, the same ids `applicableBindings` returns and a client passes back as `bindingSpecificationId`.

## Diagnostics Tie-Ins

A stored binding's wiring is also visible to the resolution and diagnostics kernel, independent of the response-layer concerns above:

- `Resolution/CandidateOrigin::Stored` — a stored `DataRequirement` whose produced type resolves and is assignable to the declared reference FQCN becomes the property's `resolved` pick directly, never a `candidates` menu entry (applied wiring is a resolution, not an offer).
- `Diagnostics/ViolationCode::MismatchedReferenceType` — a stored wiring whose produced type is **not** assignable to the declared FQCN is an intrinsic-scope error, independent of any bound root source.

## Design Note: Deliberate Duplication

This subsystem does not share code with `Layout/Element/Style/` beyond the pattern each class follows (loader trio, decorated registry, compiler pass, app tier). Each system's declaration validates against a different live registry and produces a different runtime artifact (a `DataRequirement` and seeded properties here, an `ElementStyle` there), so collapsing the two behind a shared abstraction would couple two independently evolving vocabularies for a structural resemblance only. Repeat the shape; do not factor it out.

## Subdirectories

- **Loader/** - `AbstractContentSystemBindingSpecificationLoader` (base), `YamlBindingSpecificationLoader` (filesystem), `DatabaseBindingSpecificationLoader` (app bindings in prod), `BindingSpecificationSourceDirectory` (source directory VO), `ResolvedBindingSpecificationDto` (loading-to-specification bridge)
- **Registry/** - `AbstractContentSystemBindingSpecificationRegistry` (decoration pattern contract), `ContentSystemBindingSpecificationRegistry` (stateless aggregator), `CachedContentSystemBindingSpecificationRegistry` (cross-request cache decorator)
- **Serialization/** - `BindingSpecificationSerializer` (YAML/JSON-schema ↔ DTO)
- **Specification/Dto/** - `BindingSpecificationDto` (deserialization + load-time validation shape, carries both class-level constraints) and its collection
- **Validation/** - `WellFormedBindingSpecification` (+ validator, structural shape), `TypeConsistentBindingSpecification` (+ validator, §6 live-registry semantics)
