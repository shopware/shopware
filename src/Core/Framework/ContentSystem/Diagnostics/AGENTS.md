> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `LayoutAnalysis` constructor order: `report` first, then `resolutions`.
- `ViolationCode` is the only place scope and severity are defined — do not derive them anywhere else.
- Consumer-scope-aware satisfaction: `brokenChainViolations` satisfies a required `parent`-scope consumer only from an available entry with `root: false` and a `root`-scope consumer only from one with `root: true`, matching keys exact-or-dot-path via `ContextPathResolver::matches()` (the delivery predicate, so a required dotted consumer delivery would resolve is not reported broken). The split exists because the uniform availability formula appends the root-ambient set at every depth while delivery hands root-ambient values to root-scoped consumers alone; without it a required parent-scope consumer for a root-only key would pass the gate and render nothing. The two violation messages distinguish the cases.
- Primitive-property satisfaction is strict: `propertyBindingViolation` flags a required primitive as `UnresolvedRequired` iff `!hasStoredValue($element, $resolution->key)` (no stored value: absent key or authored null). Do not reintroduce a `|| $resolution->default === null` term: serving applies no type default, so a bare default does not satisfy; the default reaches storage via `Mutation` scaffold/replace seeding and the write-boundary `Layout/LayoutDefaultSeeder`, not at diagnosis. `PropertyResolution::default` stays populated (the editor still sees the type default) but is not consulted for satisfaction.
- `hasStoredValue()` is type-aware (it reads the element-type registry), and for a TRANSLATABLE property the anchor rule supersedes present-and-not-null: satisfaction needs a language map carrying `Defaults::LANGUAGE_SYSTEM` with a string variant, because only the anchor terminates every language chain a `SalesChannelContext` is built with. An empty string satisfies; an anchor entry holding the null variant does not, and an absent anchor key does not — distinct stored states this rule maps to one answer, so a test asserts the state rather than `assertNull`. A present, non-null map without the anchor does not satisfy. An unregistered component and an undeclared key take the untranslated rule.
- Both satisfaction rules read the one `hasStoredValue()`, so the anchor rule governs `UnresolvedRequired` and `UnfilledRequiredInput` alike; a translatable property is a valid binding `inputs` target, so a map without the anchor reads as unfilled there too.
- `MismatchedPropertyType` judges EVERY declared property through `Layout/Type/Specification/PropertyType::admits()`, with no private match table and no null short-circuit ahead of the call. Do not reintroduce either: `Layout/Codec/PropertyTypeConformanceValidator` and `Mutation/Op/ReplaceElement` read the same predicate, and a private copy diverges silently. The predicate's own semantics are in [Layout/Type/docs/symbols.md](../Layout/Type/docs/symbols.md).
- Any constraint-level defect at the DAL write masks every violation this module would report, because field-serializer constraints run before `PreWriteValidationEvent` — so `Validation/ContentLayoutWriteValidator` → `Validation/LayoutGate` → `LayoutDiagnostics` never runs and the response carries the constraint error alone. Pre-existing DAL ordering, with the call chain, in [Validation/AGENTS.md](../Validation/AGENTS.md).
- `RootContextMapper::resolveType()` throws `ContentSystemException` for unregistered source or unknown entity; `LayoutDiagnostics` catches client-defect codes and converts them to `InvalidConfig` violations.
- `LayoutDiagnostics` carries `@internal` on the class.
- What each per-violation check reports and when it fires: [docs/violation-rules.md](docs/violation-rules.md)

## Navigation

- Symbol index: classes, roles, paths — [docs/symbols.md](docs/symbols.md)
- Per-violation reporting rules — [docs/violation-rules.md](docs/violation-rules.md)
- The violation model and the full code mapping table — [README.md](README.md)
