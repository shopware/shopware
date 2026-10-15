# Client Defects and Internal Faults

Which error codes mean the client's layout input was wrong, and why a status code alone cannot answer that.

`ContentSystemException::isClientDefect()`, backed by `CLIENT_DEFECT_CODES`, marks the fifteen codes that signal a defect in client-supplied layout input rather than an internal fault:

| Code | Means |
|---|---|
| `UNKNOWN_LOADER_ENTITY` | a typo'd entity |
| `INVALID_FIELD_VALUE_TYPE` | undecodable or invalid config |
| `INVALID_FIELD_VALUE_RANGE` | a field value outside its permitted range |
| `DATA_LOADER_NOT_REGISTERED`, `CONFIG_SERIALIZER_NOT_REGISTERED` | an unregistered source |
| `CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE`, `PROPERTY_ALIAS_WITH_DOT_NOTATION`, `PROPERTY_ALIAS_COLLISION`, `REDISTRIBUTE_DOTTED_PATH`, `REDISTRIBUTE_CONFLICT`, `ROOT_SCOPE_WITH_REDISTRIBUTE` | an element-definition context-wiring violation |
| `PROVIDER_DELIVERY_COLLISION` | a child-facing provider-key collision |
| `INVALID_MAP_KEY` | a numeric wiring key |
| `INVALID_ELEMENT_ID` | an element id outside the decode gate's value domain |
| `BOX_SPACING_INVALID_VALUE` | box-spacing input exceeded a PCRE processing limit or had invalid encoding |

The enforcement sites for the element-definition context-wiring codes are in [../Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md).

The catalogue is consumed at several boundaries:

- `Diagnostics/LayoutDiagnostics` maps caught per-element defects to `ViolationCode::InvalidConfig`.
- `Api/DraftLayoutDecoder` maps codec defects to `invalidLayoutStructure` in strict decoding, or collects `invalid_config` violations in lintable decoding.
- DAL layout writes map catalogued codec and write-boundary defects to a 400 write rejection. The serializer separately maps `INVALID_LAYOUT_STRUCTURE` from its duplicate-ID check.
- `Resolution/ElementResolver` treats caught defects as no stored candidate or an incomplete config.
- `Binding/Validation/TypeConsistentBindingSpecificationValidator` turns caught defects into constraint violations.

Other `ContentSystemException` codes propagate unchanged, preserving their status and code.

## Box-spacing failures

Box-spacing normalization reports `BOX_SPACING_INVALID_VALUE` (400) for PCRE backtrack, recursion or JIT stack limits and malformed UTF-8/UTF-8 offsets. Other PCRE engine failures report `BOX_SPACING_TOKENIZATION_FAILED` (500). This applies to trim, split and match operations for registered `box-spacing` options, including values processed before their declared length constraint is evaluated.

Style normalization runs outside the draft decoder's per-element codec catch. Consequently, a box-spacing processing-limit failure aborts preview and diagnose with a direct `BOX_SPACING_INVALID_VALUE` 400: preview does not wrap it as `invalidLayoutStructure`, and diagnose does not return a 200 `invalid_config` result. The DAL write boundary does wrap this client defect as a write rejection.

## Why Not Filter by Status Code

Because the two do not line up. Some HTTP 500 codes — `DATA_LOADER_NOT_REGISTERED` and `INVALID_FIELD_VALUE_TYPE` among them — are legitimate client defects for a client-supplied tree. A status-code filter would classify those as server faults and hide a correctable mistake from the author.
