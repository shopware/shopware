# Diagnostics

Produces a `LayoutAnalysis` for a layout element tree: per-element property resolutions plus a `DiagnosticsReport` that classifies every defect by scope and severity. Two predicates on the report gate the two lifecycle transitions — `isWellFormed()` blocks persistence, `isResolvable()` blocks serving.

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
| `DanglingLanguage` | Intrinsic | Warning |
| `UnresolvedRequired` | Binding | Error |
| `AmbiguousRequired` | Binding | Error |
| `BrokenRequiredChain` | Binding | Error |
| `UnfilledRequiredInput` | Binding | Error |
| `UnresolvedOptional` | Binding | Warning |

The per-violation reporting rules, `MismatchedReferenceType`, `DanglingLanguage` and `UnfilledRequiredInput` included, are in [docs/violation-rules.md](docs/violation-rules.md).
