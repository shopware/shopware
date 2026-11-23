
# Inline mapping parser

The parser action requires `content_layout:read` and returns token spans from `InlineMappingTokenParser`.
Offsets are UTF-8 byte offsets within each submitted text.
The client supplies a non-negative `revision` and must discard results that no longer match its editor's revision.
Token recognition stays on the server.

## Request and response

`POST /api/_action/content-system/inline-mapping/parse-batch` accepts an ordered list of 1 to 50 requests.
Each entry contains `text` and a non-negative `revision`.
Parsing one field uses a list containing one entry.
Unknown attributes at either level are rejected.

```json
{
    "requests": [
        { "text": "First field", "revision": 1 },
        { "text": "Second field", "revision": 4 }
    ]
}
```

The response contains a `results` list with one entry per request, in the same order.
Each successful entry contains its echoed `revision` and `tokens`.
Duplicate texts and revisions remain separate entries and are parsed independently.
There is no result cache.
See the [batch schema](../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-inline-mapping-parse-batch.json).

## Errors

| Condition | Response |
| --- | --- |
| Missing privilege | HTTP 403 |
| Invalid request shape, revision, or batch size | HTTP 400 for the whole request |
| Text containing mapping syntax exceeds 256 KiB | HTTP 200 with `{ "revision": 1, "error": { "code": "CONTENT_SYSTEM__INLINE_MAPPING_INPUT_TOO_LARGE", "message": "..." } }` for that entry; other entries are parsed |
| Parser engine fault | HTTP 500, `CONTENT_SYSTEM__INLINE_MAPPING_PARSING_FAILED`; no successful partial response |

The Administration service batches parse calls queued in the same JavaScript turn and splits groups larger than 50 fields.
Each caller receives its own promise result or error, so editor revision checks and error handling remain local to that field.
