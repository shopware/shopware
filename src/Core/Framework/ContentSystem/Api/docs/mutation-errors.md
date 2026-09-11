# Mutation Errors

The failure conditions that abort a stateless draft mutation action ([mutation.md](mutation.md)) instead of being reported in its diagnostics body.

A resolvability problem (an unresolved required property, a broken context chain) is reported in the `diagnostics` body at HTTP 200, not as an error. Only the conditions below abort the request (`ContentSystemException`); the structural impossibilities are `400 Bad Request`:

| Condition                                                                                                | HTTP | Factory                                           |
|----------------------------------------------------------------------------------------------------------|------|---------------------------------------------------|
| Missing/invalid envelope field                                                                           | 400  | `#[MapRequestPayload]` validation (forced to 400) |
| `update-element-properties`: both `values` and `removeKeys` empty                                        | 400  | `Validation/UpdateElementPropertiesNotEmpty` constraint, on the draft and the persisted route alike. Like every envelope 400, the body carries no error code; the `updateElementPropertiesEmpty` token in the message identifies it |
| A referenced element id is not in the layout                                                             | 400  | `mutationTargetNotFound`                          |
| Moving an element into itself or a descendant                                                            | 400  | `mutationCycle`                                   |
| Inserting into a parent, moving under a different parent, or wrapping, without naming the target slot    | 400  | `mutationSlotRequired`                            |
| Wrap targets are empty, or not in one container (must be siblings in a single slot, or all root-level)   | 400  | `mutationInvalidWrapTargets`                      |
| `type` / `newType` / `containerType` is not a registered element type                                    | 400  | `mutationUnknownType`                             |
| `bindingSpecificationId` is not a registered binding specification                                       | 400  | `bindingSpecificationNotFound`                    |
| The binding specification's declared `type` does not match the target element's `component`              | 400  | `bindingTypeMismatch`                             |
| `update-element-properties`: a key in `values` or `removeKeys` does not name a primitive property the type declares | 400 | `mutationPropertyUnknown` |
| `update-element-properties`: a key is present in both `values` and `removeKeys`                           | 400  | `mutationPropertyConflict`                        |
| `update-element-properties`: a `values` entry does not satisfy `PropertyType::admits()` for its declared type | 400 | `mutationPropertyValueRejected`                |
| `update-element-properties`: a translatable property's language map carries a key that is not a language id in lowercase UUID hex (key existence stays a `dangling_language` diagnostics warning). The first offending key reports, per the first-failing-rule contract — unlike the DAL constraint pass, which enumerates every offending key | 400 | `mutationPropertyLanguageKeyInvalid`           |
| `insert-element` or `replace-element` on a type whose default binding specification set holds more than one (only reachable via a database row created outside the app lifecycle) | 409 | `bindingSpecificationDefaultAmbiguous`            |
| Layout element missing a non-empty string `id`/`component`; a duplicate element `id`, nesting past the maximum depth, or a non-array nested child (rejected before the edit runs); or an element config that is a client defect | 400 | `invalidLayoutStructure`                          |
| `rootSource` is a non-empty value not registered in `RootSourceRegistry`                                 | 400  | `unknownRootSource` (the route gates membership against `RootSourceRegistry::knownRootSources()` before resolving, the same as the write validator) |

Known limitation, deliberately left unfixed: a `values` entry carrying a JSON number no finite PHP float can represent (`1e400` decodes to `float(INF)`) aborts with HTTP 500 (`CONTENT_SYSTEM__INVALID_FIELD_VALUE_TYPE`) instead of the 400 `mutationPropertyValueRejected` above, on the draft and the persisted route alike. Only hand-crafted JSON from an authenticated client can produce such a literal (`JSON.stringify` cannot emit it), the request is rejected either way, and nothing is written — so the wrong status class was judged not worth a guard on the ingress path. A direct entity or Sync write shares the limitation through a narrower door: a list-shaped `layout` payload is decoded during the write's own normalize step, which turns the throw into a 400 write rejection, but a non-list `layout` payload skips that decode and reaches the constraint pass raw, where the same throw escapes as the same 500.

The mutation-structural codes above — `mutationTargetNotFound`, `mutationCycle`, `mutationSlotRequired`, `mutationInvalidWrapTargets`, `mutationUnknownType`, `mutationPropertyUnknown`, `mutationPropertyConflict`, `mutationPropertyValueRejected`, `mutationPropertyLanguageKeyInvalid` — are not client defects; see [Client-Defect Error Codes](../../docs/client-defect-codes.md).
