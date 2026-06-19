@README.md

## Source Code References

- `LayoutResolvabilityValidator` — exposes the two gate entry points `wellFormedness()` / `resolvability()` (each runs `LayoutDiagnostics::analyze()`, which returns a `Diagnostics/LayoutAnalysis`, and returns its `->report` half — a `Diagnostics/DiagnosticsReport`, whose `isWellFormed()` / `isResolvable()` are the actual predicates — they live on the report, not here) plus the `isBindingEnforced()` binding-enforcement seam; `const SKIP_VALIDATION_STATE = 'content-system-skip-layout-validation'` bypasses both gates when set via `Context::addState`; `@internal` constructor
- `ContentLayoutWriteValidator` — `kernel.event_subscriber` on `PreWriteValidationEvent`; well-formedness gate on `content_layout` writes + synchronous re-check of already-bound layouts via tagged `Binding/LayoutBindingEnumerator`s
- `ContentLayoutAssignmentWriteValidator` — `kernel.event_subscriber` on `PreWriteValidationEvent`; binding gate for entity-assignment writes; delegates load-and-check to `LayoutBindingGate`
- `LayoutBindingGate` — shared load-and-check core; `bindingViolations(mixed $contentLayoutId, list<ProvidedContext> $providedRootContext, Context): ConstraintViolationList`; returns empty list when layout is not yet loadable (same-transaction Sync case); consumed by both `ContentLayoutAssignmentWriteValidator` and Storefront's `HeaderFooterAssignmentWriteValidator`
- `ViolationConstraintMapper` — maps `list<Violation>` to `ConstraintViolationList`; property-path format `/{elementId}` or `/{elementId}/{key}`; places `Violation::$code->value` (a `Diagnostics/ViolationCode` string) in the Symfony constraint `code` field, which `WriteConstraintViolationException::getErrors()` surfaces as the `code` key of each write-error object

## Constraints

- `SKIP_VALIDATION_STATE` suppresses **both** gates (`ContentLayoutWriteValidator` and `ContentLayoutAssignmentWriteValidator`); set by migrations and trusted bulk importers; absent on all normal write paths including the Sync API
- Gate predicates (`isWellFormed()`, `isResolvable()`) live on `Diagnostics/DiagnosticsReport`; `LayoutResolvabilityValidator` calls `LayoutDiagnostics::analyze()` (which returns a `LayoutAnalysis`) and returns its `->report` (`DiagnosticsReport`) — it does not own the predicates
- `LayoutResolvabilityValidator::isBindingEnforced(BoundRootContext): bool` defaults to `true`; the single overridable seam for a future draft/versioning system to exempt non-live bindings
- Sync same-transaction gap: when a layout and its binding are created in one Sync batch, `LayoutBindingGate::loadTree()` returns `null` (layout not yet committed) — the binding check is skipped; the bound-layout re-check in `ContentLayoutWriteValidator` closes this gap on the next layout edit
- Both `ContentLayoutWriteValidator` and `ContentLayoutAssignmentWriteValidator` are registered as `kernel.event_subscriber` in `content-system.xml`; Storefront registers its own `HeaderFooterAssignmentWriteValidator` using the same `LayoutBindingGate`
- `ViolationConstraintMapper` accumulates all violations into one list; a batch write reports every violation rather than short-circuiting on the first
