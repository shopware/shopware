# Diagnostics

Produces a `LayoutAnalysis` for a layout element tree: per-element property resolutions plus a `DiagnosticsReport` that classifies every defect by scope and severity. The report carries the gate predicates `isWellFormed()` and `isResolvable()`; where each applies: [drafts-and-gates.md](../docs/principles/drafts-and-gates.md).

## Key Classes

- `LayoutDiagnostics` - Entry point. Pass `null` as the root context to run the intrinsic subset only (well-formedness check without a bound source).
- `LayoutAnalysis` - Output value object: the report plus the per-element property resolutions.
- `DiagnosticsReport` - Holds the public readonly `$violations` (`list<Violation>`, the full unfiltered defect set read directly by consumers such as `Api/ContentDiagnoseController`). Gate predicates: `isWellFormed()` (no intrinsic-scope Error violations), `isResolvable()` (no binding-scope Error violations).
- `Violation` - A single defect. Scope and severity derive from the code.
- `ViolationCode` - Enum, string-backed. The "single source of truth" for scope and severity.
- `RootContextMapper` - `map()` converts a bound source's data requirements into the root-ambient context fed to `analyze()`. `resolveType()` returns the concrete FQCN a requirement's configured loader produces and throws `ContentSystemException` for an unregistered source or unknown entity; `LayoutDiagnostics` calls it inline and catches the client-defect codes to detect invalid loader config.

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
| `UnresolvedOptional` | Binding | Warning |

The per-violation reporting rules, `MismatchedReferenceType` and `UnfilledRequiredInput` included, are in [docs/violation-rules.md](docs/violation-rules.md).
