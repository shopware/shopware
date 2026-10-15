# OpenAPI DTO test fixtures

These rules apply to all parser, renderer, and generator tests in this directory.

- Every valid generation scenario MUST have its own directory under `_fixtures/<scenario>/`, containing a complete OpenAPI JSON document and every resulting generated PHP DTO or enum beside it.
- Assert the complete generated PHP output and the complete set of generated files;
  do not replace fixture coverage with selected property, constructor, package, or source-string assertions.
- Keep schema JSON and expected PHP output together; do not introduce separate schema-only directories
- Do not inline schema documents in PHP tests or construct the scenario by modifying another fixture at runtime.
- Generate expected PHP output through `bin/console open-api:generate-dtos`; never write or edit generated DTOs manually.
- Keep separate executable tests only for behavior that source snapshots cannot verify, such as serialization, validation, omission versus explicit `null`, or generation errors.
- Those executable tests MUST reuse the adjacent JSON/PHP fixtures whenever the scenario can generate valid output.
- Invalid schemas belong under `_fixtures/invalidSchemas/` as complete JSON documents, with explicit exception assertions instead of expected PHP DTOs.
- Run the relevant fixture tests after changes; fix stale fixtures instead of excluding them from the test cases.
