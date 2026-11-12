# Definition-load errors

Invalid installed definitions are server configuration faults when a runtime registry loads them. App installation catches these Content System exceptions and wraps them as app-validation HTTP 400 responses; manifest validation collects schema errors.

| Failure | Runtime HTTP status | Error code suffix (after `CONTENT_SYSTEM__`) |
| --- | --- | --- |
| Element-type collective validation or file/row load failure | 500 | `ELEMENT_TYPE_LOAD_FAILED` |
| Style-option collective validation or file/row load failure | 500 | `STYLE_OPTION_LOAD_FAILED` |
| Binding collective validation or file/row load failure | 500 | `BINDING_SPECIFICATION_LOAD_FAILED` |
| Preset collective validation | 500 | `LAYOUT_PRESETS_INVALID` |
| Preset file/row load failure | 500 | `LAYOUT_PRESET_LOAD_FAILED` |
| Invalid definition filename | 500 | `ELEMENT_TYPE_INVALID_FILENAME`, `STYLE_OPTION_INVALID_FILENAME`, `LAYOUT_PRESET_INVALID_FILENAME` |
| Binding sugar cannot be expanded or declares an unknown type | 500 | `BINDING_SPECIFICATION_CANONICALIZATION_FAILED`, `BINDING_SPECIFICATION_UNKNOWN_TYPE` |
| Duplicate names, reserved binding IDs or ambiguous default bindings | 409 | The existing specific conflict code |

The validation and filename/canonicalization codes retain their identities. They are not added to the client-defect catalogue. Client-authored layout validation still returns the request-specific 400 or diagnostics result. Uncatalogued exceptions crossing the DAL layout serializer preserve their own status and code, including registry conflicts (409).
