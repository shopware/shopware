# Validation

DAL `PreWriteValidationEvent` gate for content layouts. Two subscribers enforce the served-implies-resolvable invariant: `ContentLayoutWriteValidator` is the single ordered `content_layout` write gate (well-formedness → root-source membership → resolvability against the declared root source), while `ContentLayoutAssignmentWriteValidator` is a tree-blind type-match that rejects an assignment whose layout's immutable `root_source` does not match the assignment's entity type.

The assignment type-match guarantees served-implies-resolvable only while the resolvability inputs a layout was validated against stay stable after its write: the bound definition's `getPageDataRequirements()` (its `providedRootContext`) and the live element-type registry, as [context-wiring.md](../docs/principles/context-wiring.md#the-write-gate-computes-each-delivery-rule-it-checks-exactly-as-serving-computes-it) states.

## Writes that skip the gate

`LayoutGate::SKIP_VALIDATION_STATE`, added to the write `Context` via `Context::addState`, makes `ContentLayoutWriteValidator` and `ContentLayoutAssignmentWriteValidator` return early. It is meant for a trusted bulk import that has pre-validated its layouts. No in-repo path sets it: migrations write raw SQL through `Connection`, and the Sync API does not set it.

A write with a constraint-level defect never reaches the gate. `EntityWriter::upsert()` throws field-serializer constraint errors before `PreWriteValidationEvent` is raised, so a payload that trips a `Layout/Codec/StoredTreeConstraints` constraint (for example an unknown option) fails with that constraint error alone and no diagnostics violations. This DAL ordering is outside the module.

## Key Classes

- `LayoutGate` — the layout gate: a persistence gate and a serving gate, each returning a `Diagnostics/DiagnosticsReport` whose `isWellFormed()` / `isResolvable()` are the actual predicates; never throws; holds the `SKIP_VALIDATION_STATE` constant
- `ContentLayoutWriteValidator`: the single ordered `content_layout` write gate: well-formedness on the tree the layout field serializer left on the write `Context` (`Layout/LayoutWriteContext`) after admitting it through `Layout/LayoutWriteBoundary`, root-source membership in `Adapter/RootSourceRegistry` (early return on a miss), then resolvability against the declared root source's context (on a layout-only edit against the committed `root_source` read through `LayoutRootSourceReader`, whose membership is re-checked first so a source removed from the registry after the write is an `unknownRootSource` 400 on the next edit instead of an unrepairable 500)
- `ContentLayoutAssignmentWriteValidator` — a tree-blind type-match on entity-assignment writes: reads the bound layout's immutable `root_source` (via `LayoutRootSourceReader`) and rejects the write (`ContentSystemException::rootSourceAssignmentMismatch`, 400) when it does not equal the assignment definition's entity type
- `LayoutRootSourceReader` — shared read of a layout's immutable `root_source` at write time (in-flight write batch first, then committed row), used by the Core write and assignment validators and Storefront's header/footer validator; returns `null` when the layout is not yet loadable (left to the FK constraint)
- `ViolationConstraintMapper`: converts `Diagnostics/Violation`s to Symfony `ConstraintViolation`s with property paths in `/{elementId}` or `/{elementId}/{key}` format (the key segment is omitted for element-level violations); the `ViolationCode` string value is the constraint `code` field and the `code` key in the write-error API payload; accumulates all violations into one list, so a batch write reports every one
