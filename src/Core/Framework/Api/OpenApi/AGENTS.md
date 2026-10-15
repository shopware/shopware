# API DTO contract

These rules apply when changing request or response DTOs, their shared base classes, OpenAPI schemas, or DTO generation.

- Request and response DTOs MUST remain mutable so third-party extension events can adjust them;
  do not make their classes or properties `readonly`.
- `extensions` is reserved for optional third-party plugin data inherited from `AbstractDto`, independently of schema-defined business fields.
- OpenAPI schemas MUST NOT list `extensions` in `required`.
- Generated DTOs MUST NOT redeclare `extensions`, promote it, or require it as a constructor argument.
- Empty extension data may remain absent or be unset by the extension helpers, because `extensions` is always optional.
