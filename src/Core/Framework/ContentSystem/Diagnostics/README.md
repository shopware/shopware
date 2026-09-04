# Diagnostics

Produces a `LayoutAnalysis` for a layout element tree: per-element property resolutions plus a `DiagnosticsReport` that classifies every defect by scope and severity. Two predicates on the report gate the two lifecycle transitions — `isWellFormed()` blocks persistence, `isResolvable()` blocks serving.

## Key Classes

- `LayoutDiagnostics` - Entry point. `analyze(array $tree, ?array $rootContext): LayoutAnalysis`. Pass `null` for `$rootContext` to run the intrinsic subset only (well-formedness check without a bound source).
- `LayoutAnalysis` - Output value object. Holds `DiagnosticsReport $report` and `array $resolutions` (element id → `list<PropertyResolution>`).
- `DiagnosticsReport` - Holds the public readonly `$violations` (`list<Violation>`, the full unfiltered defect set read directly by consumers such as `Api/ContentDiagnoseController`). Gate predicates: `isWellFormed()` (no intrinsic-scope Error violations), `isResolvable()` (no binding-scope Error violations). Also provides `intrinsicErrors()` and `bindingErrors()`.
- `Violation` - A single defect: `ViolationCode $code`, `string $elementId`, `?string $key`, `string $message`, `list<ResolutionCandidate> $candidates`. Scope and severity derive from the code.
- `ViolationCode` - Enum, string-backed. The single source of truth for scope and severity.
- `RootContextMapper` - `map(array<DataRequirement>): list<ProvidedContext>` converts a bound source's data requirements into the root-ambient context fed to `analyze()`. `resolveType(DataRequirement): string` returns the concrete FQCN a requirement's configured loader produces and `@throws ContentSystemException` for an unregistered source or unknown entity; `LayoutDiagnostics` calls it inline and catches the client-defect codes to detect invalid loader config.

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

`MismatchedReferenceType` flags a stored reference wiring (any `DataRequirement` the element carries, not only one recorded in `attributedSpecifications`) whose resolved produced type is not assignable to the property's declared FQCN. It is intrinsic, not binding-scope: the mismatch is a property of the element's own stored wiring, independent of any bound root source. A config that fails to resolve (a client defect) is `InvalidConfig` instead; a config that resolves and fits produces no violation — `Resolution/ElementResolver` reports it as a `Stored` resolution.

`UnfilledRequiredInput` flags a required reference resolved by its own stored wiring (a `Resolution/CandidateOrigin::Stored` pick) whose loader config still references an element property that holds no value — the reference resolves, yet the element would serve empty. The rule reads the source's declared config specification and fires per **required** `propertyReference` config key whose configured value is a string naming a property with no stored value (a stored explicit `null` counts as no value). It is keyed on the input property (the control the Admin highlights) and names both keys in the message; when the configured name is not a declared primitive property of the type — stored wiring is never property-name validated — it keys on the reference property instead, still naming both keys with the same neutral "has no value" message: an undeclared storage key with an empty value is the normal pre-fill state of a resolvedBy reference, and a typo'd key is indistinguishable from it. A required reference satisfied by parent context or a loader candidate never reaches this rule, and defaulted (`required: false`) `propertyReference` keys (the navigation shape) never gate. No loader source name appears in the rule; loader-dependence is expressed entirely through the config specification's `propertyReference` kind.
