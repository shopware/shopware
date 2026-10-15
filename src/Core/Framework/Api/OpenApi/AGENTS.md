# API DTO contract

These rules apply when changing request or response DTOs, their shared base classes, OpenAPI schemas, or DTO generation.

- Request and response DTOs MUST remain mutable so third-party extension events can adjust them;
  do not make their classes or properties `readonly`.
- Required fields MUST remain mandatory constructor arguments, including nullable fields and fields with schema defaults.
- Optional fields MUST remain uninitialized until explicitly assigned in request, response, and nested/shared DTOs.
- Schema defaults, including `null`, and single-value enums MUST NOT initialize DTO properties;
  apply business defaults explicitly in the calling code.
- Explicit schema defaults MUST be available as public `DEFAULT_<FIELD_NAME>` constants, using uppercase words separated by underscores.
- Generate no default constant when `default` is absent;
  an explicit `default: null` MUST generate a constant with value `null`.
- `extensions` is reserved for optional third-party plugin data inherited from `AbstractDto`, independently of schema-defined business fields.
- OpenAPI schemas MUST NOT list `extensions` in `required`.
- Generated DTOs MUST NOT redeclare `extensions`, promote it, or require it as a constructor argument.
- Empty extension data may remain absent or be unset by the extension helpers, because `extensions` is always optional.
