## Constraints

- `LayoutAnalysis` constructor order: `report` first, then `resolutions`.
- `ViolationCode` is the only place scope and severity are defined — do not derive them anywhere else.
- Consumer-scope-aware satisfaction: `brokenChainViolations` satisfies a required `parent`-scope consumer only from an available entry with `root: false` and a `root`-scope consumer only from one with `root: true`, matching keys exact-or-dot-path via `ContextPathResolver::matches()` (the delivery predicate, so a required dotted consumer delivery would resolve is not reported broken). The split exists because the uniform availability formula appends the root-ambient set at every depth while delivery hands root-ambient values to root-scoped consumers alone; without it a required parent-scope consumer for a root-only key would pass the gate and render nothing. The two violation messages distinguish the cases.
- Primitive-property satisfaction is strict: `propertyBindingViolation` flags a required primitive as `UnresolvedRequired` iff `!hasStoredValue($element, $resolution->key)` (no stored value: absent key or authored null). Do not reintroduce a `|| $resolution->default === null` term
- Any constraint-level defect at the DAL write masks every violation this module would report, because field-serializer constraints run before `PreWriteValidationEvent` — so `Validation/ContentLayoutWriteValidator` → `Validation/LayoutGate` → `LayoutDiagnostics` never runs and the response carries the constraint error alone. Pre-existing DAL ordering, with the call chain, in [Validation/AGENTS.md](../Validation/AGENTS.md).
- Turn a client-defect exception met during `analyze()` into an `InvalidConfig` violation and let every other code propagate. Check: `LayoutDiagnosticsTest` pins the conversion and the propagation.
- `LayoutDiagnostics` carries `@internal` on the class.
- What each per-violation check reports and when it fires: [docs/violation-rules.md](docs/violation-rules.md)
- Reject a draft only on well-formedness, never on `bindingErrors()`. Enforce those only on a `content_layout` write. Check: `DraftLayoutCheckerTest` and `ContentLayoutWriteValidatorTest` pin both halves.
- Compute every delivery rule the gate checks (child-facing key, consumer key matching, provider collision) with the code serving uses. Check: does the gate reuse `ProviderDeliveryKeyResolver` and `ContextPathResolver::matches()`?

## Navigation

- Per-violation reporting rules — [docs/violation-rules.md](docs/violation-rules.md)
- The violation model and the full code mapping table — [README.md](README.md)
- Why a draft is held to well-formedness, and why diagnostics report rather than reject: [drafts-and-gates.md](../docs/principles/drafts-and-gates.md)
- Why these constraints hold, and what was not chosen: [context-wiring.md](../docs/principles/context-wiring.md)
