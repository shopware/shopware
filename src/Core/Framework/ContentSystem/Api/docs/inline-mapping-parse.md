
# Inline mapping parser

The parser action requires `content_layout:read` and returns token spans from `InlineMappingTokenParser`.
Offsets are UTF-8 byte offsets within the submitted text.
The client supplies a non-negative `revision` and must discard results that no longer match its editor's revision.
Token recognition stays on the server.

## Request and response

`POST /api/_action/content-system/inline-mapping/parse` accepts one `text` and a non-negative `revision`.
Unknown attributes are rejected.

```json
{
    "text": "Hello {{map:product.name}}",
    "revision": 1
}
```

The response contains the echoed `revision` and a `tokens` list.
See the [wire schema](../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-inline-mapping-parse.json) for each token's fields.

## Errors

| Condition | Response |
| --- | --- |
| Missing privilege | HTTP 403 |
| Invalid request shape or revision | HTTP 400 |
| Text containing mapping syntax exceeds 256 KiB | HTTP 400, `CONTENT_SYSTEM__INLINE_MAPPING_INPUT_TOO_LARGE` |
| Parser engine fault | HTTP 500, `CONTENT_SYSTEM__INLINE_MAPPING_PARSING_FAILED`; no successful partial token result |

## Administration request scheduling

The Administration service queues individual parse requests in order of arrival, with at most three requests in flight.
Each completed or failed request releases its slot for the next field.
Results and errors belong to that field's promise, so a failed parse does not reject other fields.
Identical texts and revisions remain separate requests and are parsed independently.
There is no result cache.
Editor revision checks continue to discard stale responses.
