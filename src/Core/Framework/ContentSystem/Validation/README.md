# Validation

DAL `PreWriteValidationEvent` gate for content layouts. Two subscribers enforce the served-implies-resolvable invariant: `ContentLayoutWriteValidator` blocks persistence of structurally invalid layouts and prevents edits that would make a live layout unresolvable, while `ContentLayoutAssignmentWriteValidator` blocks bindings to layouts that the bound source cannot resolve.

## Key Classes

- `LayoutResolvabilityValidator` — the two gate entry points `wellFormedness(tree, Context)` (persistence gate) and `resolvability(tree, providedRootContext, Context)` (serving gate), each returning a `Diagnostics/DiagnosticsReport` whose `isWellFormed()` / `isResolvable()` are the actual predicates; holds the `SKIP_VALIDATION_STATE` constant; `isBindingEnforced(BoundRootContext)` is the seam for future draft-versioning exemptions
- `ContentLayoutWriteValidator` — well-formedness gate on `content_layout` writes; re-checks all bound sources via tagged `Binding/LayoutBindingEnumerator`s
- `ContentLayoutAssignmentWriteValidator` — binding gate on entity-assignment writes; delegates to `LayoutBindingGate`
- `LayoutBindingGate` — shared load-and-check core used by Core assignment validator and Storefront's header/footer validator; returns empty violations when the layout is not yet loadable (same-transaction Sync case)
- `ViolationConstraintMapper` — converts `Diagnostics/Violation`s to Symfony `ConstraintViolation`s with property paths in `/{elementId}` or `/{elementId}/{key}` format (the key segment is omitted for element-level violations); the `ViolationCode` string value rides in the constraint `code` field, surfacing as the `code` key in the write-error API payload
