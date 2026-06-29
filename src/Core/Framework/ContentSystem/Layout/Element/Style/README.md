# Style

Universal style option system. A defined set of presentation attributes (alignment, span, spacing, display) settable on **every** content element regardless of its type, served through the Store API and extensible by plugins and apps. Each option is per-breakpoint by default; an option opts out with `breakpointAware: false` to take a single flat scalar value instead. It mirrors the element type system (`Layout/Type/`) one directory at a time, varying only the subject: an option is a declarative specification, discovered from core, bundles, plugins, and apps, aggregated by one cache-decorated registry that both validation and introspection read.

## Universal, Not Per-Type

Style options are strictly universal: every defined option is valid on every element, with no backend per-type gating. Where an element type declares its own `properties` and `slots`, a style option declares nothing about which elements it applies to. Visibility hints (for example, showing `col-span` only inside a grid) are an Admin concern carried in the option's opaque `adminUI` block, not a backend rule.

This is why an option name lives in a **flat global namespace**: the name is the Store-API wire key (`col-span`), not a source-prefixed type name (`Sw:Grid`). Uniqueness is still enforced — by the loader and registry dedup at load time, by `StyleOptionCollisionDetector` at app install time, and by a DB `UNIQUE KEY` — only the prefix is dropped.

## The Option Model

- `StyleOptionSpecification` — the immutable declared contract of one option: its `name` (wire key), its `StyleOptionValueType`, a `breakpointAware` flag, an optional `adminUI` passthrough block, and a `source` label. `breakpointAware` defaults to `true` (an option opts out with `breakpointAware: false`); `true` makes the value a per-breakpoint map, `false` a single flat scalar. `toSchema()` serializes it for introspection and always emits `breakpointAware` (before `adminUI`).
- `StyleOptionValueType` — the value vocabulary: one canonical primitive (`string`, `integer`, `number`, `boolean`) plus declarative bounds (`enum`, numeric `range`, string `maxLength`) and an advisory `default`. Style values are always primitives, so there is no FQCN, no nesting, and no regex; a `maxLength` (defaulting to 255 for an unbounded string or number) caps what a client can store in the layout JSON.
- `Breakpoint` — the fixed canonical key set `xs, sm, md, lg, xl, xxl`. Per-breakpoint values are individually optional, so a responsive option may set only `md`.
- `ElementStyle` — the per-element value object: a validated `option => (scalar | breakpoint => scalar)` map. A breakpoint-aware option holds a per-breakpoint map (`"col-span": {"md": 6}`); a flat option holds a bare scalar (e.g. a forward-looking `"z-index": 10`). A plain immutable DTO (not a `Struct`), emitted as a raw array via `ContentElement::jsonSerialize()`, omitted when empty.

A style option's `default` is **advisory** — an introspection and Admin pre-fill hint only. It is never seeded into stored element JSON and never applied at serve time; serving renders the stored `style` verbatim, consistent with the content system's "no default applied at serve" rule. This keeps `style` omitted-when-empty rather than bloating every element with universal defaults.

## Strict Write, Registry-Free Read

`Layout/Field/ElementStyleFieldSerializer` is the boundary. Only the write path reads the registry; it derives the validation constraints fresh per write (the parent serializer reuses that one built tree across every element in the write):

- **Write is strict, per flag.** An unknown option key, an unknown breakpoint, or a value that violates the option's derived constraints (`type` / `enum` / `range` / `maxLength`) is rejected. The shape is also enforced per `breakpointAware`, both directions: a breakpoint-aware option sent as a bare scalar is rejected, and a flat option sent as a breakpoint map is rejected. The field serializer composes the flag with the breakpoint-unaware constraint deriver: a breakpoint-aware option becomes a per-breakpoint `Collection`, a flat one a single `Optional($valueConstraints)`. Constraint derivation reads the strict `registry->all()`, so a cross-loader name collision fails the write and install paths hard.
- **Read is registry-free and structural.** `deserialize()` never consults the registry: a scalar value is kept flat, an array value is cleaned into a canonical breakpoint map (each key a `Breakpoint`, each value a scalar; an empty map is dropped). This is unambiguous because every value type is a primitive. Unknown option names ride through verbatim. A layout written while a plugin or app option was registered still renders after that provider is removed, and a cross-loader name collision never reaches the read path. This mirrors the element type system's unknown-`component` handling — kept verbatim on read, tolerated at resolve, rejected only on write. Re-saving such a layout is rejected until the orphaned option is cleared, so a normal edit round-trip no longer auto-clears it.

The Symfony constraints and the introspection schema are both derived from the one declaration, so the two cannot drift: `StyleOptionConstraintDeriver` turns a `StyleOptionValueType` into a `list<Constraint>` via the fluent `ConstraintBuilder`.

## Architecture

1. **Specification Value Objects** (`Specification/`) — immutable VOs `StyleOptionSpecification` and `StyleOptionValueType`. `Specification/Dto/` carries the Symfony validation DTOs (`StyleOptionSpecificationDto`, its collection) that validate the well-formedness of a declaration at load.

2. **Loading** (`Loader/`) — both loaders extend `AbstractContentSystemStyleOptionLoader`. `YamlStyleOptionLoader` handles core, bundle, and plugin options in every environment plus app options in dev; it resolves the option name from the kebab-case filename, deserializes via `StyleOptionSpecificationSerializer`, validates the DTOs, and deduplicates within and across directories. `DatabaseStyleOptionLoader` loads active app options from `app_content_system_style_option` in prod and returns empty in dev. `StyleOptionSourceDirectory` carries source and path per directory; `ResolvedStyleOptionSpecificationDto` bridges loading and specification creation.

3. **Registry** (`Registry/`) — the Shopware decoration pattern. `AbstractContentSystemStyleOptionRegistry` defines the contract: the strict `all()` (a cross-loader duplicate throws), the lenient `allResolved()` (a cross-loader duplicate resolves silently by source precedence; the strict `all()` is the loud surface that throws), and `invalidate()`. `ContentSystemStyleOptionRegistry` is the stateless aggregator (leaf) that iterates loaders tagged `content_system.style_option_loader`; `CachedContentSystemStyleOptionRegistry` decorates it with a `cache.system` pool, caching `all()` and `allResolved()` under separate keys that `invalidate()` both clears. `invalidate()` throws `DecorationPatternException` by default; only the cached decorator overrides it.

4. **Serialization** (`Serialization/`) — `StyleOptionSpecificationSerializer` converts a declaration between its YAML/array form and the validation DTO.

5. **Validation** (`Validation/`) — `StyleOptionConstraintDeriver` (declaration to runtime constraints), `StyleOptionCollisionDetector` (proposed names against the registry plus inactive app options), and `TypedStyleOption` (+ validator), the class-level constraint that checks a declaration is internally consistent: `enum` / `range` / `maxLength` / `default` agree with the declared type, the `default` also stays within the declared bounds, and `adminUI` is an array.

6. **Compiler Pass** — `Framework/DependencyInjection/CompilerPass/ContentSystemStyleOptionCompilerPass` discovers YAML directories from core `Definitions/`, each bundle's and each active plugin's fixed `Resources/content-system/style-options`, and (dev only) each active app's directory, then injects them into `YamlStyleOptionLoader`. Unlike the element-type pass, the convention directory is fixed for both bundles and plugins, so the core `Plugin` base class needs no customization hook.

7. **App Integration** — `App/Aggregate/AppContentSystemStyleOption/` (DAL entity, table `app_content_system_style_option`), `App/Lifecycle/Persister/ContentSystemStyleOptionPersister` (hash-based upsert/delete in one transaction with collision detection, invalidating the registry only after a committed change), `App/Lifecycle/Handler/ContentSystemStyleOptionLifecycleHandler` (persists on install/update, and invalidates the cached registry on activate, deactivate, uninstall, and local delete; activation does not pre-validate for a collision — it cannot fail atomically once `active=1` is committed, so the strict `all()` surfaces a cross-loader collision loudly on the next write/install), and `App/Validation/ContentSystemStyleOptionAppValidator` (manifest validation). `DatabaseStyleOptionLoader` joins `app` and filters `WHERE app.active = 1`, so deactivating an app drops its options from that query with no extra write, and the lifecycle handler invalidates the cached registry on that transition so the cached set reflects it immediately; removal cascades through the FK.

## Introspection

`StyleOptionSpecification::toSchema()` feeds two surfaces, both from the one registry, so introspection and validation never drift:

- a dedicated endpoint `GET /api/_info/content-system-style-options.json` (`InfoController::getContentSystemStyleOptions()`), serving the options keyed by their wire name;
- a folded `styleOptions` key on `GET /api/_info/content-system-element-types.json`, since every option is settable on every type.

`toSchema()` (the client contract) always emits `breakpointAware` resolved to a concrete bool, so a client never has to assume the default. `normalize()` (internal DB storage of an app option) follows the `!== null` convention shared with `enum` / `range` / `maxLength` / `default` instead: it omits an absent flag but emits an explicit `false`. Both resolve to the same effective value (absent ⇒ `true`).

## Output

A per-element `ElementStyle` rides through the system without any per-operation awareness. The mutation primitives (`Mutation/AbstractLayoutMutation::rebuildNode` / `cloneWithNewIds`, and `Mutation/Op/ReplaceElement`) carry it across every structural edit. `ContentElement::jsonSerialize()` emits it for the full format, and `Output/Struct/ContentSkeletonElement` carries it so it survives the skeleton and decomposed formats; the data (properties-only) format omits it. In every format `style` is omitted when empty, so it never serializes as an empty object.

## Subdirectories

- **Definitions/** - Core YAML option definitions (7 files): `display` (boolean), `align-self` / `justify-self` (string enum), `col-span` / `row-span` (integer range), `margin` / `padding` (string with `maxLength`)
- **Loader/** - Option loading: `AbstractContentSystemStyleOptionLoader` (base), `YamlStyleOptionLoader` (filesystem), `DatabaseStyleOptionLoader` (app options in prod), `StyleOptionSourceDirectory` (source directory VO), `ResolvedStyleOptionSpecificationDto` (loading-to-spec bridge)
- **Registry/** - `AbstractContentSystemStyleOptionRegistry` (decoration pattern contract), `ContentSystemStyleOptionRegistry` (stateless aggregator), `CachedContentSystemStyleOptionRegistry` (cross-request cache decorator)
- **Serialization/** - `StyleOptionSpecificationSerializer` (YAML/array ↔ DTO)
- **Specification/** - Value objects (`StyleOptionSpecification`, `StyleOptionValueType`)
- **Specification/Dto/** - Validation DTOs with Symfony constraint attributes
- **Validation/** - `StyleOptionConstraintDeriver` (declaration → constraints), `StyleOptionCollisionDetector` (unique-name guard), `TypedStyleOption` (+ validator, declaration consistency)
