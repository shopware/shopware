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
| `UnresolvedOptional` | Binding | Warning |

`MismatchedReferenceType` flags a stored reference wiring (any `DataRequirement` the element carries, not only one recorded in `attributedSpecifications`) whose resolved produced type is not assignable to the property's declared FQCN. It is intrinsic, not binding-scope: the mismatch is a property of the element's own stored wiring, independent of any bound root source. A config that fails to resolve (a client defect) is `InvalidConfig` instead; a config that resolves and fits produces no violation — `Resolution/ElementResolver` reports it as a `Stored` resolution.

`UnfilledRequiredInput` flags a required reference resolved by its own stored wiring (a `Resolution/CandidateOrigin::Stored` pick) whose loader config still references an element property that holds no value — the reference resolves, yet the element would serve empty. The rule reads the source's declared config specification and fires per **required** `propertyReference` config key whose configured value is a string naming a property with no stored value (a stored explicit `null` counts as no value). It is keyed on the input property (the control the Admin highlights) and names both keys in the message; when the configured name is not a declared primitive property of the type — stored wiring is never property-name validated — it keys on the reference property instead, still naming both keys with the same neutral "has no value" message: an undeclared storage key with an empty value is the normal pre-fill state of a resolvedBy reference, and a typo'd key is indistinguishable from it. A required reference satisfied by parent context or a loader candidate never reaches this rule, and defaulted (`required: false`) `propertyReference` keys (the navigation shape) never gate. No loader source name appears in the rule; loader-dependence is expressed entirely through the config specification's `propertyReference` kind.
