# Client-Defect Error Codes

- Client-defect classification: `ContentSystemException::isClientDefect()` (`CLIENT_DEFECT_CODES`) marks the error codes that signal a defect in client-supplied layout input: a typo'd entity (`UNKNOWN_LOADER_ENTITY`), undecodable/invalid config (`INVALID_FIELD_VALUE_TYPE`), a field value outside its permitted range (`INVALID_FIELD_VALUE_RANGE`), an unregistered source (`DATA_LOADER_NOT_REGISTERED`, `CONFIG_SERIALIZER_NOT_REGISTERED`), an element-definition context-wiring violation (`CONSUMER_ALIAS_WITHOUT_REDISTRIBUTE`, `PROPERTY_ALIAS_WITH_DOT_NOTATION`, `PROPERTY_ALIAS_COLLISION`, `REDISTRIBUTE_DOTTED_PATH`, `REDISTRIBUTE_CONFLICT`, `ROOT_SCOPE_WITH_REDISTRIBUTE`; the alias, redistribute and scope enforcement sites: [Layout/Element/Context/AGENTS.md](../Layout/Element/Context/AGENTS.md)), a child-facing provider-key collision (`PROVIDER_DELIVERY_COLLISION`), a numeric wiring key (`INVALID_MAP_KEY`), an element id outside the value domain of `StoredElementCodec::decode()` (`INVALID_ELEMENT_ID`), or box-spacing input that exceeds a PCRE processing limit or carries invalid encoding (`BOX_SPACING_INVALID_VALUE`), rather than an internal fault. Every other code propagates unchanged, with its status and code. See [The cause of a defect sets its classification, and HTTP status is a separate axis](principles/failure-and-loss.md#the-cause-of-a-defect-sets-its-classification-and-http-status-is-a-separate-axis).

## Where the Classification Is Read

- `Diagnostics/LayoutDiagnostics` maps a caught per-element defect to `ViolationCode::InvalidConfig`.
- `Api/DraftLayoutDecoder` maps a codec defect to `invalidLayoutStructure` in strict decoding and collects it as an `invalid_config` violation in lintable decoding.
- The DAL layout write maps a codec or write-boundary defect to a 400 write rejection. `Layout/Field/StoredElementListFieldSerializer` separately maps `INVALID_LAYOUT_STRUCTURE` from its duplicate-id check.
- `Resolution/ElementResolver` treats a caught defect as no stored candidate or an incomplete config.
- `Binding/Validation/TypeConsistentBindingSpecificationValidator` turns a caught defect into a constraint violation.

## Box-Spacing Failures

Box-spacing normalization reports `BOX_SPACING_INVALID_VALUE` (400) for a PCRE backtrack, recursion or JIT stack limit and for malformed UTF-8 or a malformed UTF-8 offset. Any other PCRE engine failure reports `BOX_SPACING_TOKENIZATION_FAILED` (500). This holds for the trim, split and match operations of every registered `box-spacing` option, also for a value processed before its declared length constraint is checked.

Style normalization runs outside the draft decoder's per-element codec catch. A box-spacing processing-limit failure therefore aborts preview and diagnose with a direct `BOX_SPACING_INVALID_VALUE` 400: preview does not wrap it as `invalidLayoutStructure`, and diagnose does not return a 200 `invalid_config` result. The DAL write boundary wraps it as a write rejection.

## Mapping Validation

Keep mapping-specific admission codes such as `PROPERTY_NOT_MAPPABLE`, `UNKNOWN_MAPPING_PATH`, and `MAPPING_TYPE_MISMATCH` outside `CLIENT_DEFECT_CODES`. `Mapping/StoredMappingInspector` reports whole-field and inline admission problems through `MappingProblem`. `LayoutDiagnostics` converts those findings into property-specific `ViolationCode::InvalidMapping` entries in successful diagnosis/mutation responses; the mapping write gate rejects invalid persisted mappings. These paths do not depend on the generic client-defect exception handlers.

Dedicated mapping actions can also reject an invalid request with HTTP 400, for example `INVALID_MAPPING_SOURCE`. That response does not require membership in `CLIENT_DEFECT_CODES`. Shared structural codes, including `PROPERTY_ALIAS_COLLISION`, retain their existing classification when reached from a mapping action. Administration translations select messages by response code independently of this list.

An internal `MapProperty` call on an unbound tree reports `MAPPING_ROOT_SOURCE_MISSING` (500), outside `CLIENT_DEFECT_CODES`. Mapping routes validate or load their source before applying the operation; missing/blank or unknown request sources still receive HTTP 400. Unbound drafts remain valid for structural editing.

Runtime inline-output exhaustion remains an HTTP 500 failure outside this list, and provider/internal failures continue to propagate through normal error handling. Any future expansion of the list must identify a concrete generic catch site that needs the new classification and test that author-correctable input receives feedback while provider/internal faults still propagate.
