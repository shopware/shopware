# Validation

DAL `PreWriteValidationEvent` gate for content layouts. Two subscribers enforce the served-implies-resolvable invariant: `ContentLayoutWriteValidator` blocks persistence of structurally invalid layouts and prevents edits that would make a live layout unresolvable, while `ContentLayoutAssignmentWriteValidator` blocks bindings to layouts that the bound source cannot resolve.

## Key Classes

- `LayoutGate` — the layout gate: the two gate predicates `wellFormedness(tree, Context)` (persistence gate) and `resolvability(tree, providedRootContext, Context)` (serving gate), each returning a `Diagnostics/DiagnosticsReport` whose `isWellFormed()` / `isResolvable()` are the actual predicates; never throws; holds the `SKIP_VALIDATION_STATE` constant; `isBindingEnforced(SourceBinding)` is the seam for future draft-versioning exemptions
- `ContentLayoutWriteValidator` — well-formedness gate on `content_layout` writes; re-checks all bound sources via tagged `Binding/LayoutBindingEnumerator`s
- `ContentLayoutAssignmentWriteValidator` — the serving-gate validator on entity-assignment writes; runs the binding check via `LayoutBindingChecker`
- `LayoutBindingChecker` — shared binding-check core (applies the resolvability gate to a layout referenced by id, asking `LayoutGate`) used by the Core assignment validator and Storefront's header/footer validator; returns empty violations when the layout is not yet loadable (same-transaction Sync case)
- `ViolationConstraintMapper` — converts `Diagnostics/Violation`s to Symfony `ConstraintViolation`s with property paths in `/{elementId}` or `/{elementId}/{key}` format (the key segment is omitted for element-level violations); the `ViolationCode` string value rides in the constraint `code` field, surfacing as the `code` key in the write-error API payload
