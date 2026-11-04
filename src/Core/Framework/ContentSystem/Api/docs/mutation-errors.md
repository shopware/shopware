# Mutation Errors

A resolvability problem (an unresolved required property, a broken context chain) is reported in the `diagnostics` body at HTTP 200, not as an error. Only the conditions below abort the request (`ContentSystemException`). The structural impossibilities are `400 Bad Request`:

| Condition                                                                                                | HTTP | Factory                                           |
|----------------------------------------------------------------------------------------------------------|------|---------------------------------------------------|
| Missing/invalid envelope field                                                                           | 400  | `#[MapRequestPayload]` validation (forced to 400) |
| `map-property` source has missing, invalid, or unknown fields                                             | 400  | `invalidMappingSource` (`CONTENT_SYSTEM__INVALID_MAPPING_SOURCE`) |
| `map-property` targets a property without mapping opt-in, an unavailable source, or an incompatible value type | 400 | `propertyNotMappable`, `unknownMappingPath`, `mappingTypeMismatch` |
| `map-property` conflicts with an ordinary consumer's destination/base key or would overwrite its consumer key | 400 | `propertyAliasCollision` (`CONTENT_SYSTEM__PROPERTY_ALIAS_COLLISION`) |
| An inline-mapped property exceeds 256 KiB | 400 | `inlineMappingInputTooLarge` (`CONTENT_SYSTEM__INLINE_MAPPING_INPUT_TOO_LARGE`) |
| Inline token scanning or path validation fails in the regular-expression engine | 500 | `inlineMappingParsingFailed` (`CONTENT_SYSTEM__INLINE_MAPPING_PARSING_FAILED`); the request aborts instead of returning incomplete diagnostics |
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
| Collective validation fails or a per-file/per-row load step raises the matching `*_LOAD_FAILED` code for an element-type, style-option, or binding registry used by the operation | 500 | The corresponding `ELEMENT_TYPE_LOAD_FAILED`, `STYLE_OPTION_LOAD_FAILED`, or `BINDING_SPECIFICATION_LOAD_FAILED` error propagates |

Mapping findings discovered during analysis are returned as property-specific `invalid_mapping` entries in the HTTP 200 diagnostics body of diagnosis and stateless draft-mutation responses. This includes a malformed inline path without a member below its source. `malformedInlineMappingToken` (`CONTENT_SYSTEM__MALFORMED_INLINE_MAPPING_TOKEN`) supplies the finding's message; analysis does not throw that exception. Saving an invalid mapping is separately rejected by the persistence write gate with HTTP 400.

The authored-input limit above is an actual throwing validation failure. Runtime inline-output exhaustion is separate: the content pipeline aborts with `CONTENT_SYSTEM__INLINE_MAPPING_EXPANSION_TOO_LARGE`. Store API and preview propagate it as HTTP 500 and return no successful partial or blanked content result. Storefront content loading instead catches that exception and returns a null content page, allowing its caller to render a successful response without that content. See [runtime error handling](../../Mapping/README.md#runtime-error-handling) for the request boundaries and provider-fault behavior.

Runtime definition failures also include invalid filenames and binding canonicalization/type errors (500 with their specific codes). Preset-loading operations additionally expose preset load and validation errors. See [definition-load errors](../../docs/definition-load-errors.md) for the code/status mapping and app-install distinction.

The mutation-structural codes above (`mutationTargetNotFound`, `mutationCycle`, `mutationSlotRequired`, `mutationInvalidWrapTargets`, `mutationUnknownType`) are not client defects. See [Client-Defect Error Codes](../../docs/client-defect-codes.md).
