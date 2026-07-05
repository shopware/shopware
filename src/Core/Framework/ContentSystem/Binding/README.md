# Binding

The binding specification system. A binding specification is an authored declaration wiring one element type's reference properties to data loaders and seeding its primitive properties, so an editor (or an agentic layout builder) can apply a complete, pre-validated data wiring to an element in one action instead of hand-assembling loader configs. It mirrors the universal style option system (`Layout/Element/Style/`) one directory at a time, varying only the subject: a specification is a declarative artifact, discovered from core, bundles, plugins, and apps, aggregated by one cache-decorated registry that both validation and introspection read.

## Not a Root Source

"Binding" names a different relationship than "root source" (`Adapter/RootSourceRegistry`): a root source is the registered origin of a layout's root-ambient context (an entity type, a section, or "none"); a binding is the relationship between one reference property and the source that fills it — the sense `Diagnostics/ViolationScope::Binding` already carries. A `BindingSpecification` authors such a binding for one element type; it says nothing about what a layout's root is bound to. See `../NAMING.md`.

## The Specification Model

- `BindingSpecification` — the immutable declared contract of one binding: its `id`, the element `type` it applies to, a human `label`, a `resolves` map (reference property key → `LoaderBinding`), and an `inputs` map (primitive property key → `BindingInput`). `toSchema()` serializes it for introspection.
- `LoaderBinding` — one `resolves` entry: a data loader `source` plus its `config`. Becomes a `Layout/Element/DataRequirement/DataRequirement` when applied to an element.
- `BindingInput` — one `inputs` entry: an optional typed default for a primitive property, with presence modeled explicitly (`hasDefault()`) so "no default" is distinct from "default is null".

A specification's `resolves`/`inputs` keys are validated at load time against the declared type's actual properties, so an applied specification can never target a property the type does not have.

## The Promoted Flag

A specification may carry an optional `promoted: true` boolean (inline or standalone; absent means `false`). `BindingSpecification::isPromoted()` exposes it and `toSchema()` always emits `promoted: bool`.

It is **pure catalog metadata**. No server behavior reads it: the scaffold never auto-applies it, the write gate never consults it, and `bind-element` ignores it. It exists so a client (the Admin picker, an agent) can learn which specification the author promotes for a type — for example to present the promoted binding as *the* "Image" entry and the raw type as the advanced option. The binding choice stays an explicit client act; the metadata only informs it.

The invariant is **at most one promoted specification per element type across the aggregated registry**, enforced in three tiers that mirror the type system's collision philosophy (hard for authored YAML, manifest-gated plus a backstop for apps):

- **Hard, in the YAML loader.** `YamlBindingSpecificationLoader::load()` throws `ContentSystemException::bindingSpecificationPromotedDuplicate` (409) when two specifications in its loaded set — standalone or inline, any source — promote the same type. In dev this covers all sources (apps load from the filesystem); in prod it covers core, bundles, and plugins.
- **Soft, in the app validator.** `App/Validation/ContentSystemBindingSpecificationAppValidator` reports a schema error (never an exception) when an app specification promotes a type the aggregated registry already promotes, or when the app promotes one type twice across its own two authoring forms. This check is **inherently incomplete**: its data source is the aggregated registry, which sees only **active** apps' rows (`DatabaseBindingSpecificationLoader` filters `active = 1`), and apps install **inactive** by default — so ordinary install-then-activate ordering (install app A inactive, validate and install app B against a registry that cannot see A, then activate both; activation never re-validates) reaches the conflict without any race. The backstop below covers what slips through.
- **Demote-and-warn, at aggregation.** `Registry/ContentSystemBindingSpecificationRegistry::all()` runs a backstop after the full merge: when more than one promoted specification survives for one type (app-vs-YAML or app-vs-app), it keeps one deterministic winner and demotes the rest in the aggregated result, logging a `warning` per demotion — the same drop-and-warn resilience the DB loader applies to poison rows, not a hard throw. **Winner rule:** an authored (YAML-sourced) flag beats a persisted (DB-sourced) one; within one origin class the lexicographically smallest source-qualified id wins. Loader origin is classified by loader class (`$loader instanceof DatabaseBindingSpecificationLoader` → DB-sourced, anything else → authored), so no new facet rides on `BindingSpecification` and no loader source name is tested. A demotion constructs a `promoted: false` copy via `BindingSpecification::withoutPromotion()`. `CachedContentSystemBindingSpecificationRegistry` caches this already-demoted aggregate, so no reconciliation happens downstream.

A plugin overriding a *core* promoted specification is intentionally impossible in this cut; an explicit replacement mechanism is a future item.

## Authoring Sugar and Canonicalization

An author can write a `resolves` entry in one of three shapes; `Serialization/BindingSpecificationCanonicalizer` expands the first two into the third at load time, between `Serialization/BindingSpecificationSerializer::denormalize()` and constraint validation, so the two validators only ever see canonical `{loader, config}` entries. No canonicalization rule names a loader source — every tier decision keys off the loaders' declared config specifications (`Hydration/DataLoader/LoaderConfigSpecification`), so an extension loader that declares the same `ConfigKeyKind`s participates identically.

- **Tier A — a bare property-reference string** (`media: mediaId`): the canonicalizer resolves the reference property's declared FQCN, then selects the single loader source whose capability produces that FQCN exactly and whose config specification has exactly one required `propertyReference` key (the string fills it); every other required key is either supplied by that capability's `configTemplate` or, for an `entityName`-kind key, derived from the FQCN.
- **Tier B — the single-key loader form** (`media: { entity: { property: mediaId } }`): the one key names the loader source, its value is the flat config; every required `entityName`-kind key absent from that config is derived from the reference property's FQCN. A config key the loader does not declare is a load-time error.
- **Tier C — the canonical form** (`media: { loader: entity, config: { entity: media, property: mediaId } }`): passed through unchanged; the only shape for unusual configs.

Every specification the canonicalizer processes — sugared or already canonical — must declare a registered element type; an unknown type is a hard load error (`ContentSystemException::bindingSpecificationUnknownType`, 400). Any sugar that cannot expand deterministically (an unrecognized entry shape, a `loader` key mixed with a loader-source key, zero or several eligible tier-A sources, zero or several entities producing the FQCN, an unknown tier-B config key) is a hard load error (`ContentSystemException::bindingSpecificationCanonicalizationFailed`, 400) whose message carries the mechanical fix. Sugar never resolves by precedence and never degrades to a best guess. `DatabaseBindingSpecificationLoader` never canonicalizes: app rows are persisted canonical by construction.

### The Sugar Stability Contract

Tier A's loader eligibility and the FQCN-to-entity-name derivation both range over open registries — the installed data loaders and the registered DAL entities. Installing an extension can therefore turn a previously unambiguous tier-A entry ambiguous (a second loader that also produces the FQCN exactly, or a second entity that produces the same class). When that happens the canonicalizer fails loudly with the tier-B/explicit-`entityName` fix in the message; it never silently re-resolves. The alternative to failing would be a precedence rule between candidate expansions, and precedence is exactly what this system refuses to own.

Be precise about *when* that failure surfaces. The binding registry builds lazily: `Registry/CachedContentSystemBindingSpecificationRegistry::all()` populates the cache on first read, `invalidate()` only deletes the cache key, and no cache warmer populates it (its cache key is referenced only by the cached registry itself). So a fresh install that invalidated the cache does not rebuild it during `cache:warmup`; the ambiguity surfaces on the **first request or console command that reads the binding catalog after the invalidation**, not at deploy time. A pipeline that wants the failure before first traffic needs a smoke step that reads the catalog.

Tier B entries are stable against a newly installed loader (the source is named in key position) and against a new entity once the `entityName` key is authored explicitly. Tier C entries and all persisted app rows are canonical, so they are immune.

## Two Validators, Two Concerns

Every declaration passes two constraints, at two levels:

- **`Validation/WellFormedBindingSpecification`** — class-level on `BindingSpecificationDto`, structural shape only: `type`/`label` non-blank, `resolves`/`inputs` (if present) are arrays of arrays, each `resolves` entry declares a non-blank `loader`, each `inputs` entry's `default` (if present) is a scalar or `null`. No registry lookups. Runs per dto via the collection's `#[Assert\Valid]` cascade.
- **`Validation/TypeConsistentBindingSpecification`** — class-level on `BindingSpecificationDtoCollection` (not the dto), semantic consistency against live registries: it iterates the collection's dtos and, for each, resolves the declared `type` **overlay-first then registry** (the collection carries a per-load `typeOverlay`, empty on every non-app path); every `resolves` key names a reference property the type actually has, and the configured loader's produced type is assignable to that property's declared FQCN; every `propertyReference` config key of any loader names a primitive property; every `inputs` key names a primitive property, and a present `default` matches that property's declared type. Violation paths are keyed on the binding id (`bindings[<id>].…`).

The constraint lives on the collection because a per-load type overlay cannot ride through the dependency-injected `ValidatorInterface`, but it can be a field on the validated object that the validator reads. Splitting shape (dto) from semantics (collection) still mirrors the element-type and style-option systems' "shape first, then live-registry consistency" load-time gate, so a malformed declaration and a declaration that targets a real but wrong property fail with distinctly diagnosable violations.

## Loading, Registry, App Integration

Three loading tiers, one registry, discovered by one compiler pass — the same shape as `Layout/Element/Style/`:

1. **Loading** (`Loader/`) — both loaders extend `AbstractContentSystemBindingSpecificationLoader`. `YamlBindingSpecificationLoader` handles core, bundle, and plugin bindings in every environment plus app bindings in dev; it deserializes each YAML file via `Serialization/BindingSpecificationSerializer`, validates the DTOs, and deduplicates within and across directories (`ContentSystemException::bindingSpecificationDuplicate`). It reads two directory kinds, discriminated by `BindingSpecificationSourceDirectory::$prefix`: a `null` prefix is a **standalone** directory (one whole-file specification per file, `loadDtosFromDirectory()`); a non-null prefix is an **element-type** directory scanned for inline `bindings:` sections (`loadInlineDtosFromTypeDirectory()`, see below). `DatabaseBindingSpecificationLoader` loads active app bindings from `app_content_system_binding_specification` in prod and returns empty in dev. `BindingSpecificationSourceDirectory` carries source, path, and the nullable prefix per directory; `ResolvedBindingSpecificationDto` bridges loading and specification creation.
2. **Registry** (`Registry/`) — the Shopware decoration pattern. `AbstractContentSystemBindingSpecificationRegistry` defines the contract: `all()` (keyed by source-qualified id, `source:id`), `byType(type)`, `get(qualifiedId)`, and `invalidate()`. `ContentSystemBindingSpecificationRegistry` is the stateless aggregator (leaf) over loaders tagged `content_system.binding_specification_loader`; `CachedContentSystemBindingSpecificationRegistry` decorates it with a `cache.system` pool.
3. **App Integration** — `App/Aggregate/AppContentSystemBindingSpecification/` (DAL entity, table `app_content_system_binding_specification`, `UNIQUE (app_id, name)` — bindings are unique only within their app, unlike the globally unique style options, so two apps may legitimately ship the same bare id), `App/Lifecycle/Persister/ContentSystemBindingSpecificationPersister` (scans both the app's `Resources/content-system/binding-specifications` files and the inline `bindings:` sections of its `Resources/content-system/types`, canonicalizing both against a type overlay built from the app's own types — see "Inline bindings in an app" below; a cross-form bare-id collision throws; hash-based upsert/delete in one transaction, no cross-*source* collision check since the DB unique key is per-app), `App/Lifecycle/Handler/ContentSystemBindingSpecificationLifecycleHandler` (persists on install/update; invalidates the registry on activate/deactivate/uninstall/delete), and `App/Validation/ContentSystemBindingSpecificationAppValidator` (manifest-time schema validation of both forms, each wrapped independently, turning every `ContentSystemException` — canonicalization failure, unknown type, load failure, cross-form collision — into a `ContentSystemBindingSpecificationSchemaError` rather than an exception; within-form DB-unique-key collisions stay the loader's job).
4. **Compiler Pass** — `Framework/DependencyInjection/CompilerPass/ContentSystemBindingSpecificationCompilerPass` injects two directory sets into `YamlBindingSpecificationLoader`. The standalone set (prefix `null`): core `Definitions/`, each bundle's and each active plugin's fixed `Resources/content-system/binding-specifications`, and (dev only) each active app's directory. The element-type set (non-null prefix, scanned for inline `bindings:`): core `Layout/Type/Definitions` (prefix `Sw`), each non-plugin bundle's `Resources/content-system/types` (prefix `Sw`), each active plugin's `Plugin::getContentTypeDirectory()` (prefix = plugin name), and (dev only) each active app's `Resources/content-system/types` (prefix = app name) — mirroring `ContentSystemElementTypeCompilerPass` exactly.

## Inline `bindings:` in Element-Type Files

A specification for a type the code owns can be authored inline in that type's YAML file instead of a standalone binding-specification file, so a simple element ships as one file. The optional top-level `bindings:` key is a map of bare specification id → entry; the element-type serializer ignores unknown top-level keys, so the section is invisible to the type pipeline (no type-loader change).

- The **type is implicit** — the containing file's type name, resolved from the file path plus the directory prefix by the same `Layout/Type/Loader/ElementTypeNameResolver` the type loader uses (a file `media/image.yaml` under prefix `Sw` yields type `Sw:Media:Image`). An entry declaring an explicit `type:` is a load-time error (`bindingSpecificationCanonicalizationFailed`); so is an explicit `id:` (the map key is the id, a divergent inner copy would silently drift).
- Each entry is fed through the **same denormalize → canonicalize → validate path** as a standalone file (`['type' => <implicit>] + <entry>`), so the sugar tiers and every other facet behave identically. A `bindings:` value that is not a map, or an entry that is not a map, is a hard load error (`bindingSpecificationLoadFailed`) naming the file. Files without a `bindings:` key are skipped.
- **Uniqueness stays bare-id-per-source** (a flat namespace across all of a source's types). An inline entry and a standalone file declaring the same id in the same source throw `bindingSpecificationDuplicate`, as do two inline entries with the same id across two type files of one source. Two different sources may still each ship the same bare id.

The **YAML authoring key is `bindings:`** (shorthand inside the type file); the introspection **wire key is `bindingSpecifications`** (the folded catalog on `content-system-element-types.json`, added by a later unit). The entries are specifications, not bindings — a type has no bindings, its elements do; see the naming note in `../NAMING.md`.

### Inline bindings in an app: the type overlay

An inline binding's implicit type is always the containing element type — for an app, one of the app's own types. But canonicalization (and the semantic validator) require the declared type to be *registered*, and an app is installed **inactive**, so at install and at `manifest:validate` time neither `DatabaseTypeLoader` (`WHERE active = 1`) nor the compiler pass (dev, active apps only) surfaces the app's own types yet. Without help, every app inline binding — and every standalone app binding on the app's own type — would fail with `bindingSpecificationUnknownType`.

The app persister and app validator close this by building a **type overlay** from the app's own types (`YamlTypeLoader::loadOverlayFromDirectory()` on `Resources/content-system/types`, keyed by resolved type name) and passing it to both load calls. `BindingSpecificationCanonicalizer::canonicalize()` and the collection's `TypeConsistentBindingSpecification` resolve the declared type **overlay-first, then registry**. Every non-app path passes an empty overlay and is unchanged; DB-sourced rows validate with an empty overlay because the app is active by the time its rows are read.

**Documented limitation (not mitigated, mirrors the promoted-flag incompleteness):** an app binding targeting its **own** type always resolves (it is in the overlay); one targeting **another** app's type resolves only if that other app is **active** at install/validate time (its type is in the registry). A binding on an inactive third-party app's type is reported as an unknown type. Reconciling this needs a cross-app aggregated type view the app lifecycle does not currently build.

## Applying a Binding

`BindingApplicator` owns the merge that applies one specification's wiring onto an element. It rebuilds the element (keeping its id, component, slots, context definitions, and style — never mutating it, so the mutation immutability invariant holds) carrying:

- Each `resolves` entry becomes a concrete `DataRequirement`, merged into the element's existing data requirements and **overwriting** the same keys — re-applying a binding over an already-bound key replaces its wiring.
- Each `inputs` entry with a default seeds that primitive property, but only when the element does not already carry the key (`ContentElement::hasProperty()`, not a null check, so an authored explicit `null` is never overwritten).
- Every wired key's attribution is recorded into the element's `attributedSpecifications` map (also merged, overwriting), so a client can later ask "which specification wired this key".

Two mutation operations drive the applicator, both through the same draft/persisted mutation machinery as every other structural edit:

- `Mutation/Op/BindElement` (the ninth `Mutation/Op`) applies a specification onto an existing element. Exposed by the draft `POST /api/_action/content-system/layout/bind-element` and the persisted `POST /api/_action/content-system/layout/{layoutId}/bind-element`.
- `Mutation/Op/InsertElement` applies an optional specification onto the freshly scaffolded element when the insert-element request carries a `bindingSpecificationId`, atomically in the same edit — the specification is resolved first, before any tree change, so nothing is inserted (or, on the persisted route, persisted) when it fails.

Both ops perform the specification lookup and the type check themselves (these differ per op): an unregistered `bindingSpecificationId` (`bindingSpecificationNotFound`) or a specification whose declared `type` does not match the target element's `component` (`bindingTypeMismatch`) is rejected with `400`. `BindingApplicator` is the apply-side of that decision, extracted so both ops share one merge (naming rationale in `../NAMING.md`).

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
- **Serialization/** - `BindingSpecificationSerializer` (YAML/JSON-schema ↔ DTO), `BindingSpecificationCanonicalizer` (load-time sugar expansion, registry-driven)
- **Specification/** - Value objects: `BindingSpecification` (`id`, `type`, `label`, `resolves`, `inputs`, `source`; `toSchema()` for introspection), `LoaderBinding` (`source`, `config`), `BindingInput` (`hasDefault`, `default`)
- **Specification/Dto/** - `BindingSpecificationDto` (deserialization + load-time validation shape, carries both class-level constraints) and its collection
- **Validation/** - `WellFormedBindingSpecification` (+ validator, structural shape), `TypeConsistentBindingSpecification` (+ validator, live-registry semantics)
