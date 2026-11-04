# Diagnostics

Produces a `LayoutAnalysis` for a layout element tree: per-element property resolutions plus a `DiagnosticsReport` that classifies every defect by scope and severity. The report carries the gate predicates `isWellFormed()` and `isResolvable()`; where each applies: [drafts-and-gates.md](../docs/principles/drafts-and-gates.md).

## Key Classes

- `LayoutDiagnostics` - Entry point. `analyze()` accepts the root context, a separate optional source ID, and `reportMappingProblems`. Null root context skips ordinary binding checks; source-less inline mapping diagnostics can still run. See [mapping checks](#mapping-checks).
- `LayoutAnalysis` - Output value object: the report plus the per-element property resolutions.
- `DiagnosticsReport` - Holds the public readonly `$violations` (`list<Violation>`, the full unfiltered defect set read directly by callers such as `Api/ContentDiagnoseController`). Gate predicates: `isWellFormed()` (no intrinsic-scope Error violations), `isResolvable()` (no binding-scope Error violations).
- `Violation` - A single defect. Scope and severity derive from the code.
- `ViolationCode` - Enum, string-backed. The "single source of truth" for scope and severity.
- `RootContextMapper` - `map()` converts a bound source's data requirements into the root context fed to `analyze()`. `resolveType()` returns the concrete FQCN a requirement's configured loader produces. It throws `ContentSystemException` for an unregistered source or unknown entity. `LayoutDiagnostics` calls it inline and catches the client-defect codes to detect invalid loader config.

## ViolationCode Reference

| Code | Scope | Severity |
|---|---|---|
| `UnregisteredComponent` | Intrinsic | Error |
| `DuplicateElementId` | Intrinsic | Error |
| `InvalidConfig` | Intrinsic | Error |
| `MismatchedReferenceType` | Intrinsic | Error |
| `MismatchedPropertyType` | Intrinsic | Error |
| `UnknownStyleOption` | Intrinsic | Error |
| `OrphanedProvider` | Intrinsic | Warning |
| `UnresolvedRequired` | Binding | Error |
| `AmbiguousRequired` | Binding | Error |
| `BrokenRequiredChain` | Binding | Error |
| `UnfilledRequiredInput` | Binding | Error |
| `InvalidMapping` | Binding | Error |
| `UnresolvedOptional` | Binding | Warning |

The per-violation reporting rules, `MismatchedReferenceType` and `UnfilledRequiredInput` included, are in [docs/violation-rules.md](docs/violation-rules.md).

## Mapping checks

`InvalidMapping` flags stored whole-field or inline mappings not admitted by the bound source's catalogue: missing opt-in, an unavailable source, incompatible cardinality, or an incompatible effective type. Findings are keyed on the mapped property.

The catalogue lookup needs a root source ID, which cannot be inferred from the resolved root context. Without a source ID, whole-field mappings remain unjudged. Explicit inline tokens in opted-in properties report that a bound source is required only when the root context is also null. With a supplied root context and no source ID, no mapping checks run.

Mapped references select a `Root` source before stored loader wiring, so they do not require a manual picker input. The shared mapping predicate also guards the Stored-only required-input check. Required primitives still need authored values; diagnostics does not read source values.

Admission rules live in `Mapping/StoredMappingInspector`: diagnostics reports findings, while `Validation/StoredMappingValidator` refuses writes with specific `CONTENT_SYSTEM__*` codes. See [data mapping](../Mapping/README.md) for runtime behavior.

For provider backing, analysis forwards the explicit source ID to `AvailableContextResolver`, which admits mapped reference values from catalogue metadata without reading them. The write gate also supplies the source but sets `reportMappingProblems: false` so the separate `StoredMappingValidator` retains its specific error codes. Editor diagnosis and mutation responses keep mapping-problem reporting enabled.
