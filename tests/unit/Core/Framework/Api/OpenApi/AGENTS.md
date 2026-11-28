# OpenAPI DTO test fixtures

These rules apply to all parser, renderer, and generator tests in this directory.

Fixture schemas and generated DTOs MUST follow the [production DTO contract](../../../../../../src/Core/Framework/Api/OpenApi/AGENTS.md).

- Every valid generation scenario MUST have its own directory under `_fixtures/<scenario>/`, containing a complete OpenAPI JSON document and every resulting generated PHP DTO or enum beside it.
- Assert the complete generated PHP output and the complete set of generated files;
  do not replace fixture coverage with selected property, constructor, package, or source-string assertions.
- Each valid fixture MUST run once through `OpenApiDtoGeneratorTest::testGeneratedFilesMatchAdjacentFixtures`, covering schema input through complete generated output and file paths.
- Do not repeat valid fixtures in parser or renderer tests or assert intermediate definitions, property metadata, or rendering steps already covered by that generator comparison.
- Before adding a scenario, check existing fixtures and extend the closest relevant schema when it covers the same behavior.
- Keep schema JSON and expected PHP output together; do not introduce separate schema-only directories.
- Do not inline schema documents in PHP tests or construct the scenario by modifying another fixture at runtime.
- Generate expected PHP output through `bin/console open-api:generate-dtos`; never write or edit generated DTOs manually.
- Add a separate executable test only when a specific failure cannot be detected by the complete generated PHP comparison and is not already covered by serializer, validator, or base DTO tests.
- Generated constructor arguments, property types, initializers, and validation attributes are covered by PHP fixtures;
  do not add runtime matrices merely to recheck those declarations or PHP/Symfony behavior.
- Keep parser and renderer tests focused on generation errors without valid PHP output, or behavior that demonstrably cannot be covered through the generator fixtures.
- Those executable tests MUST reuse the adjacent JSON/PHP fixtures whenever the scenario can generate valid output.
- Invalid schemas belong under `_fixtures/invalidSchemas/` as complete JSON documents, with explicit exception assertions instead of expected PHP DTOs.
- Run the relevant fixture tests after changes; fix stale fixtures instead of excluding them from the test cases.
