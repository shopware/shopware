## Navigation

- Why a draft is held to well-formedness and a gate never rewrites: [drafts-and-gates.md](../docs/principles/drafts-and-gates.md)
- Why the gate order is part of the contract and the skip state never disables conformance: [write-admission.md](../docs/principles/write-admission.md)

## Constraints

- `SKIP_VALIDATION_STATE` suppresses **both** write validators' checks (`ContentLayoutWriteValidator` and `ContentLayoutAssignmentWriteValidator` each early-return on the flag) when added to the write `Context` via `Context::addState`; intended for trusted bulk importers that have pre-validated their layouts — no in-repo path sets it (migrations bypass the DAL, writing raw SQL through `Connection`), and it is absent on all normal write paths including the Sync API
- `SKIP_VALIDATION_STATE` is not the only way the gate goes unrun: a write carrying any constraint-level defect never reaches it. Field-serializer constraints are enforced inside `encode()` → `validateIfNeeded()`, and `DataAbstractionLayer/Write/EntityWriter::upsert()` calls `tryToThrow()` before `gateway->execute()` raises `PreWriteValidationEvent` in `EntityWriteGateway::executeCommands()`, so a payload that trips e.g. the `Layout/Codec/StoredTreeConstraints` descriptor's unknown-option constraint fails with that constraint error alone and no diagnostics violations beside it. Pre-existing DAL ordering, not something this module can reorder
- Gate predicates (`isWellFormed()`, `isResolvable()`) live on `Diagnostics/DiagnosticsReport`; `LayoutGate` calls `LayoutDiagnostics::analyze()` (which returns a `LayoutAnalysis`) and returns its `->report` (`DiagnosticsReport`) — it does not own the predicates
- `ContentLayoutWriteValidator` orders membership before resolvability with an early return
- Both `ContentLayoutWriteValidator` and `ContentLayoutAssignmentWriteValidator` are registered as `kernel.event_subscriber` in `content-system.php`; Storefront registers its own `HeaderFooterAssignmentWriteValidator` using the same `LayoutRootSourceReader`
- `ViolationConstraintMapper` accumulates all violations into one list; a batch write reports every violation rather than short-circuiting on the first
