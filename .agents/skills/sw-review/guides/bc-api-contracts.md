---
guide: bc-api-contracts
title: Store API and Admin API routes are a contract with every client
personas: [architecture, security, maintainer]
rules:
  - { id: BCAPI-001, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#store-and-admin-api", origin: "https://github.com/shopware/shopware/issues/20557", fixture: "tests/guide/bc-api-contracts/catch" }
  - { id: BCAPI-002, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#store-and-admin-api", origin: "UPGRADE-6.8.md#changed-returned-status-code-for-store-apidocumentdownload-when-no-documents-are-found", fixture: "" }
  - { id: BCAPI-003, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#store-and-admin-api", origin: "https://github.com/shopware/shopware/pull/18747", fixture: "tests/guide/bc-api-contracts/catch" }
  - { id: BCAPI-004, since: 2026-10-09, source: "adr/2025-09-15-store-api-cache-strategy.md#decision", origin: "https://github.com/shopware/shopware/pull/15598", fixture: "tests/guide/bc-api-contracts/catch" }
  - { id: BCAPI-005, since: 2026-10-09, source: "adr/2023-05-10-experimental-features.md#api", origin: "adr/2023-05-10-experimental-features.md", fixture: "" }
  - { id: BCAPI-006, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#store-and-admin-api", origin: "coding-guidelines/core/backward-compatibility.md#store-and-admin-api", fixture: "" }
  - { id: BCAPI-007, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#schema-precision", origin: "https://github.com/shopware/shopware/pull/17228", fixture: "" }
  - { id: BCAPI-008, since: 2026-10-09, source: "coding-guidelines/core/backward-compatibility.md#store-and-admin-api", origin: "https://github.com/shopware/shopware/pull/20698", fixture: "" }
---

## Why this guide exists

Headless frontends, apps, ERP integrations and the Storefront itself call the Store API and Admin API. They branch on field names, status codes and error codes, and they are built against the published OpenAPI schema. The PHP controller is `@internal`, so PHP compatibility checks stay green while the HTTP contract breaks. Removing the cookie endpoints alone would affect more than 8% of customers (#20557). What counts as public is listed in [Public API is every extension surface](../../../../coding-guidelines/core/backward-compatibility.md#public-api-is-every-extension-surface).

## Check

- **BCAPI-001** Look for a route, HTTP method, route name, request parameter, header or response field that is removed or renamed, a field whose type, nullability or shape changes, and a new required request parameter. Clients keep sending the old request or reading the old field, and get errors or empty values after the update. Add the new field next to the old one and deprecate the old one. Rule: [Store and Admin API](../../../../coding-guidelines/core/backward-compatibility.md#store-and-admin-api). Example: #20557 (cookie endpoints, more than 8% of customers).
- **BCAPI-002** Look for a changed HTTP status code or error code on an existing route. Clients branch on these, so a `204` that becomes a `404` turns an expected "nothing there" into an error path. Keep the old code until the major and document the change. Rule: [Store and Admin API](../../../../coding-guidelines/core/backward-compatibility.md#store-and-admin-api). Example: UPGRADE-6.8, status code of `/store-api/document/download/`.
- **BCAPI-003** Look for a route, request or response struct that changes without a matching change under `src/Core/Framework/Api/ApiDefinition/Generator/Schema/<AdminApi|StoreApi>/paths` (or `components`), and for a deprecated route that is not marked `deprecated` in the schema. SDKs and typed clients are generated from the schema, so they do not see the new field or still expect the old one. Update the schema in the same PR. Rule: [Store and Admin API](../../../../coding-guidelines/core/backward-compatibility.md#store-and-admin-api). Example: #18747 (extended Store API struct needed a schema update), #15715 (old route not marked deprecated, new route missing).
- **BCAPI-004** Look for a new request header that a Store API route reads (`$request->headers->get(...)`). Every header that changes the response must vary the HTTP cache, which splits it into more buckets and lowers the hit rate for every shop; context belongs in the existing headers such as `sw-context-token` and `sw-language-id`, or in a parameter. Rule: [Store API cache strategy](../../../../adr/2025-09-15-store-api-cache-strategy.md#decision). Example: #15598.
- **BCAPI-005** Look for a new route that is meant to be experimental. Its OpenAPI schema needs the `Experimental` tag and a hint in the summary; without them clients assume the full BC promise. Rule: [ADR experimental features, API](../../../../adr/2023-05-10-experimental-features.md#api). Example: ADR 2023-05-10.
- **BCAPI-006** Look for changes to the `#[Route]` scope or defaults of an existing route, for example `_routeScope`, `_loginRequired`, `_httpCache` or the `_acl` privileges. They change who can call the route and how its response is cached, so existing clients get `401`/`403` or stale data. This guide owns the contract side; the security persona owns whether the new access rule is safe. Rule: [Store and Admin API](../../../../coding-guidelines/core/backward-compatibility.md#store-and-admin-api). Example: no incident recorded yet.
- **BCAPI-007** Look at the schema of a new or changed route for precision: response fields that are always sent but not `required`, request fields marked `required` that the route does not require, fixed values without `enum`, `additionalProperties` on shapes clients read, fields without a description, missing 4xx responses on Admin API routes, `401` and `403` mixed up, a parameter whose location (query or body) is unclear, a pattern that rejects values the server accepts. Generated clients then treat every field as optional, fail on valid responses or send requests the server rejects. Rule: [Schema precision](../../../../coding-guidelines/core/backward-compatibility.md#schema-precision). Example: #17228 (MCP schema with almost every field optional), #18952 (no 4xx responses, all fields optional), #21104 (`additionalProperties` without a required list).
- **BCAPI-008** Look for stricter validation on an existing route: a new pattern, a narrower list of allowed values, a new constraint, a media rule that rejects input accepted before. Integrations that worked yesterday get `400` errors after a routine update, and no signature shows it. Keep accepting the old input and put the stricter check behind the major flag. New required fields are BCAPI-001 and BCDATA-001. Rule: [Store and Admin API](../../../../coding-guidelines/core/backward-compatibility.md#store-and-admin-api). Example: #20698 (public media no longer attachable through the API), #20934 (new schema validation on product reviews).

## Do not flag

### CI covers

- `ApiRoutesHaveASchemaTest` fails for core routes without an OpenAPI schema, for method mismatches and for stale schema entries.
- Danger `RouteSnapshotExtension` fails when a new route is added to the routes-without-schema snapshot.
- The OpenAPI snapshot bot (`openapi-lint` job) posts the schema diff on the PR; Redocly lint and the Store API schema migration report fail on mismatches. For the Store API, Redocly also fails an operation without a 4xx response; the Admin API run skips that rule.
- Danger `RemovedTwigBlocks` (Storefront Twig, warning only) and ESLint are unrelated to this guide.

### Legitimate patterns

- New routes, new optional parameters and new response fields that come with a schema update.
- Changes to the PHP controller or route class that keep the HTTP contract; the controller is `@internal`.
- Routes and fields marked `@experimental` with the `Experimental` schema tag (their shape may change without deprecation; removing the whole feature in a minor is still a finding, see [Flags and experimental](../../../../coding-guidelines/core/backward-compatibility.md#flags-and-experimental)).
- `_`-prefixed and `@private` members of route classes, and test code.
- Changes behind the next major flag on trunk (`v6.8.0.0`) whose flag-off path behaves like the previous release and which carry a deprecation.

## Severity

- `blocking`: an existing route, field, parameter, status code or error code is removed, renamed or changed in a minor without deprecation, so existing clients break on update.
- `major`: a contract change without the schema update, or a new request header on a Store API route. Set `requires_human: true` when the PR argues the old contract is unused.
- `major`: stricter validation without the major flag (BCAPI-008), or a schema that marks always-sent fields optional (BCAPI-007).
- `minor`: an experimental route without the `Experimental` tag or summary hint, or a missing description or enum (BCAPI-007).

## Retired

(none yet)
