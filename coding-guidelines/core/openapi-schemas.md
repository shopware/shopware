# OpenAPI schemas

An OpenAPI schema states exactly what the server enforces. Client generators read the schema as the contract of the Admin API and the Store API. Shopware's schema files live under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/`.

## Rules

- Add a constraint such as `pattern`, `minLength`, `minimum`, `enum`, `format` or `additionalProperties: false` only when server code enforces it. The author of the constraint names the enforcing code in review.
- Check a schema against a captured response, not against the code alone. A test that validates a served body against the schema of its route performs that check.
- Document the empty shape of a map field. [Empty JSON maps](empty-json-maps.md) states the encoding.
- Give a new route a schema file under `Schema/AdminApi/paths/` or `Schema/StoreApi/paths/`. Do not add a new route to the `routes_without_schema` snapshot of `ApiRoutesHaveASchemaTest`.
- Delete a schema component that no route references. Do not repair such a component. A repair changes no client.

## Why

A schema that is stricter than the server makes a generated client reject a payload that the server accepts. A schema that is looser than the server lets a generated client send a payload that the server rejects. A server test that does not read the schema finds neither defect.

## Examples

Correct, constraint with enforcing code: in the Content System, `StoredContentElement.json` declares `"minimum": 1` for `sliceSize`. `SlicedDistributionConfig::fromArray()` rejects a smaller value instead of clamping it. The schema description says so.

Correct, captured response: `ContentRouteResponseSchemaConformanceTest` requests every Content System Store API route. `ContentRouteResponseSchemaConformanceTest` validates each served body against the schema that the route declares for its `200` response.

Incorrect (invented example): a schema declares `"minimum": 1` for a limit. The controller replaces a smaller value with 1. A generated client then rejects a limit of 0 before sending it, although the server accepts 0.

```json
"limit": { "type": "integer", "minimum": 1 }
```

```php
// Invented example. The server clamps instead of rejecting.
$limit = max(1, $request->query->getInt('limit', 10));
```

Harden the code to reject a limit below 1, or remove `minimum` when a clamped limit is the intended behavior.

## What this rule does not cover

- This rule excludes automatic detection of an unreferenced component. The Admin API schema lint in CI skips the `no-unused-components` rule. The author therefore deletes such a component by hand.
- This rule excludes the routes for which `ApiRoutesHaveASchemaTest` requires no schema file. `ApiRoutesHaveASchemaTest` requires none for the repository CRUD routes of `ApiController` and `CustomEntityApiController`. The test requires none for routes outside `Shopware\Core`. The test requires none for routes whose `PlatformRequest::ATTRIBUTE_OPENAPI` default is `false`.
