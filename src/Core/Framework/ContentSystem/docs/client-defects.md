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

`Diagnostics/LayoutDiagnostics` catches only these, per element, and maps them to a `ViolationCode::InvalidConfig` violation. Every other code propagates, so an internal fault is never relabelled as the client's mistake.

## Why Not Filter by Status Code

Because the two do not line up. Some HTTP 500 codes — `DATA_LOADER_NOT_REGISTERED` and `INVALID_FIELD_VALUE_TYPE` among them — are legitimate client defects for a client-supplied tree. A status-code filter would classify those as server faults and hide a correctable mistake from the author.
