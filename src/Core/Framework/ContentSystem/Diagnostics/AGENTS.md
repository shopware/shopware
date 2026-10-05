## Constraints

- Define a violation's scope and severity in `ViolationCode` only. Check: is any scope or severity derived outside `ViolationCode`? [README.md](README.md#violationcode-reference)
- Satisfy a required `parent`-scope consumer only from an available entry with `root: false`, and a `root`-scope consumer only from one with `root: true`. Check: does `brokenChainViolations` pick the entry half by consumer scope? [docs/violation-rules.md](docs/violation-rules.md#broken-required-chain)
- Flag a required primitive as `UnresolvedRequired` iff `!hasStoredValue($element, $resolution->key)`; never add a `|| $resolution->default === null` term. Check: does `propertyBindingViolation` consult the type default? [layout-write-gates.md](../docs/layout-write-gates.md)
- Turn a client-defect exception met during `analyze()` into an `InvalidConfig` violation and let every other code propagate. Check: does each catch test `ContentSystemException::isClientDefect()` and rethrow otherwise? [client-defect-codes.md](../docs/client-defect-codes.md)
- Reject a draft only on well-formedness, never on `bindingErrors()`; enforce those only on a `content_layout` write. Check: does the draft path keep only intrinsic errors? [drafts-and-gates.md](../docs/principles/drafts-and-gates.md#the-module-holds-a-draft-to-well-formedness)
- Compute every delivery rule the gate checks (child-facing key, consumer key matching, provider collision) with the code serving uses. Check: does the gate take available context from `AvailableContextResolver` and match consumer keys with `ContextPathResolver::matches()`? [context-wiring.md](../docs/principles/context-wiring.md#the-write-gate-computes-each-delivery-rule-it-checks-exactly-as-serving-computes-it)

## Where to look

- Violation model, gate predicates and the code to scope and severity table: [README.md](README.md#violationcode-reference)
- `UnfilledRequiredInput` and the property it is keyed on: [docs/violation-rules.md](docs/violation-rules.md#unfilled-required-input)
- `UnknownStyleOption` and which surfaces show it: [docs/violation-rules.md](docs/violation-rules.md#unknown-style-option)
- `InvalidConfig` versus `MismatchedReferenceType` for a stored data requirement: [docs/violation-rules.md](docs/violation-rules.md#stored-requirement-resolution)
- Why a constraint-level write error arrives without diagnostics violations: [docs/violation-rules.md](docs/violation-rules.md#constraint-errors-at-the-write)
- Public surface and why `LayoutDiagnostics` carries `@internal`: [extension-surface.md](../docs/principles/extension-surface.md#internalclassrule-enforces-publicness-positively)
