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
| `UnresolvedRequired` | Binding | Error |
| `AmbiguousRequired` | Binding | Error |
| `BrokenRequiredChain` | Binding | Error |
| `UnfilledRequiredInput` | Binding | Error |
| `InvalidMapping` | Binding | Error |
| `UnresolvedOptional` | Binding | Warning |

`MismatchedReferenceType` flags a stored reference wiring (any `DataRequirement` the element carries, not only one recorded in `attributedSpecifications`) whose resolved produced type is not assignable to the property's declared FQCN. It is intrinsic, not binding-scope: the mismatch is a property of the element's own stored wiring, independent of any bound root source. A config that fails to resolve (a client defect) is `InvalidConfig` instead; a config that resolves and fits produces no violation — `Resolution/ElementResolver` reports it as a `Stored` resolution.

`UnfilledRequiredInput` flags a required reference resolved by its own stored wiring (a `Resolution/CandidateOrigin::Stored` pick) whose loader config still references an element property that holds no value — the reference resolves, yet the element would serve empty. The rule reads the source's declared config specification and fires per **required** `propertyReference` config key whose configured value is a string naming a property with no stored value (a stored explicit `null` counts as no value). It is keyed on the input property (the control the Admin highlights) and names both keys in the message; when the configured name is not a declared primitive property of the type — stored wiring is never property-name validated — it keys on the reference property instead, still naming both keys with the same neutral "has no value" message: an undeclared storage key with an empty value is the normal pre-fill state of a resolvedBy reference, and a typo'd key is indistinguishable from it. A required reference satisfied by parent context or a loader candidate never reaches this rule, and defaulted (`required: false`) `propertyReference` keys (the navigation shape) never gate. No loader source name appears in the rule; loader-dependence is expressed entirely through the config specification's `propertyReference` kind.

## Mapping checks

`InvalidMapping` flags stored whole-field or inline mappings not admitted by the bound source's catalogue: missing opt-in, an unavailable source, incompatible cardinality, or an incompatible effective type. Findings are keyed on the mapped property.

The catalogue lookup needs a root source ID, which cannot be inferred from the resolved root context. Without a source ID, whole-field mappings remain unjudged. Explicit inline tokens in opted-in properties report that a bound source is required only when the root context is also null. With a supplied root context and no source ID, no mapping checks run.

Mapped references select a `Root` source before stored loader wiring, so they do not require a manual picker input. The shared mapping predicate also guards the Stored-only required-input check. Required primitives still need authored values; diagnostics does not read source values.

Admission rules live in `Mapping/StoredMappingInspector`: diagnostics reports findings, while `Validation/StoredMappingValidator` refuses writes with specific `CONTENT_SYSTEM__*` codes. See [data mapping](../Mapping/README.md) for runtime behavior.

For provider backing, analysis forwards the explicit source ID to `AvailableContextResolver`, which admits mapped reference values from catalogue metadata without reading them. The write gate also supplies the source but sets `reportMappingProblems: false` so the separate `StoredMappingValidator` retains its specific error codes. Editor diagnosis and mutation responses keep mapping-problem reporting enabled.
