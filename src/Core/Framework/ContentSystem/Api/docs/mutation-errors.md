# Mutation Errors

The failure conditions that abort a stateless draft mutation action ([mutation.md](mutation.md)) instead of being reported in its diagnostics body.

A resolvability problem (an unresolved required property, a broken context chain) is reported in the `diagnostics` body at HTTP 200, not as an error. Only the conditions below abort the request (`ContentSystemException`); the structural impossibilities are `400 Bad Request`:

| Condition                                                                                                | HTTP | Factory                                           |
|----------------------------------------------------------------------------------------------------------|------|---------------------------------------------------|
| Missing/invalid envelope field                                                                           | 400  | `#[MapRequestPayload]` validation (forced to 400) |
| `map-property` source has missing, invalid, or unknown fields                                             | 400  | `invalidMappingSource` (`CONTENT_SYSTEM__INVALID_MAPPING_SOURCE`) |
| `map-property` targets a property without mapping opt-in, an unavailable source, or an incompatible value type | 400 | `propertyNotMappable`, `unknownMappingPath`, `mappingTypeMismatch` |
| `map-property` selects a Storefront context value while the element already uses the internal context requirement key for another loader | 400 | `mappingRequirementConflict` (`CONTENT_SYSTEM__MAPPING_REQUIREMENT_CONFLICT`) |
| An inline mapping token has a malformed path without a member below its source | 400 | `malformedInlineMappingToken` (`CONTENT_SYSTEM__MALFORMED_INLINE_MAPPING_TOKEN`) |
| An inline-mapped property exceeds 256 KiB | 400 | `inlineMappingInputTooLarge` (`CONTENT_SYSTEM__INLINE_MAPPING_INPUT_TOO_LARGE`) |
| A referenced element id is not in the layout                                                             | 400  | `mutationTargetNotFound`                          |
| Moving an element into itself or a descendant                                                            | 400  | `mutationCycle`                                   |
| Inserting into a parent, moving under a different parent, or wrapping, without naming the target slot    | 400  | `mutationSlotRequired`                            |
| Wrap targets are empty, or not in one container (must be siblings in a single slot, or all root-level)   | 400  | `mutationInvalidWrapTargets`                      |
| `type` / `newType` / `containerType` is not a registered element type                                    | 400  | `mutationUnknownType`                             |
| `bindingSpecificationId` is not a registered binding specification                                       | 400  | `bindingSpecificationNotFound`                    |
| The binding specification's declared `type` does not match the target element's `component`              | 400  | `bindingTypeMismatch`                             |
| `insert-preset`: `presetId` is not a registered layout preset                                            | 404  | `layoutPresetNotFound`                            |
| `insert-preset`: a `parentElementId` without a `slot`, a parent not in the layout, or a preset root element whose type is not registered | 400 | `mutationSlotRequired`, `mutationTargetNotFound`, `mutationUnknownType`, as for `attach-element` |
| `insert-element`, `replace-element`, `attach-element` or `insert-preset` on a type whose default binding specification set holds more than one (only reachable via a database row created outside the app lifecycle) | 409 | `bindingSpecificationDefaultAmbiguous`            |
| Layout element missing a non-empty string `id`/`component`; a duplicate element `id`, nesting past the maximum depth, or a non-array nested child (rejected before the edit runs); or an element config that is a client defect | 400 | `invalidLayoutStructure`                          |
| `rootSource` is a non-empty value not registered in `RootSourceRegistry`                                 | 400  | `unknownRootSource` (the route gates membership against `RootSourceRegistry::knownRootSources()` before resolving, the same as the write validator) |
