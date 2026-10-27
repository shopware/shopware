# Client Defects and Internal Faults

Which error codes mean the client's layout input was wrong, and why a status code alone cannot answer that.

`ContentSystemException::isClientDefect()`, backed by `CLIENT_DEFECT_CODES`, marks the fourteen codes that signal a defect in client-supplied layout input rather than an internal fault:

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

The enforcement sites for the last four wiring codes are in [../Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md).

The generic exception handlers in `Diagnostics/LayoutDiagnostics` and `Api/DraftLayoutDecoder` use this list to recognize malformed layout input. Lintable decoding reports such defects as `ViolationCode::InvalidConfig`; other exceptions propagate from those handlers, so an internal fault is never relabelled as the client's mistake. Mapping validation has its own reporting path, described below.

## Why Not Filter by Status Code

Because the two do not line up. Some HTTP 500 codes — `DATA_LOADER_NOT_REGISTERED` and `INVALID_FIELD_VALUE_TYPE` among them — are legitimate client defects for a client-supplied tree. A status-code filter would classify those as server faults and hide a correctable mistake from the author.

## Mapping Validation

Keep mapping-specific admission codes such as `PROPERTY_NOT_MAPPABLE`, `UNKNOWN_MAPPING_PATH`, and `MAPPING_TYPE_MISMATCH` outside `CLIENT_DEFECT_CODES`. `Mapping/StoredMappingInspector` reports whole-field and inline admission problems through `MappingProblem`. `LayoutDiagnostics` converts those findings into property-specific `ViolationCode::InvalidMapping` entries in successful diagnosis/mutation responses; the mapping write gate rejects invalid persisted mappings. These paths do not depend on the generic client-defect exception handlers.

Dedicated mapping actions can also reject an invalid request with HTTP 400, for example `INVALID_MAPPING_SOURCE`. That response does not require membership in `CLIENT_DEFECT_CODES`. Shared structural codes, including `PROPERTY_ALIAS_COLLISION`, retain their existing classification when reached from a mapping action. Administration translations select messages by response code independently of this list.

Runtime inline-output exhaustion remains an HTTP 500 failure outside this list, and provider/internal failures continue to propagate through normal error handling. Missing or empty mapped values continue to follow the rendering rules. Any future expansion of the list must identify a concrete generic catch site that needs the new classification and test that author-correctable input receives feedback while provider/internal faults still propagate.
