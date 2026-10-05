## Constraints

- Bind each request DTO with `#[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]` on the action parameter, never on the DTO class; keep `layoutId` a route path argument. Check: no DTO class carries the attribute or a `layoutId` field. [mutation.md](docs/mutation.md#request)
- Raise every route failure as a `ContentSystemException`. Check: the condition has a row in the route's error table. [failure-and-loss.md](../docs/principles/failure-and-loss.md)
- Return draft and persisted mutation results as one `MutationResponse` that reports every detached or dropped item. Check: no edit drops content absent from the response. [failure-and-loss.md](../docs/principles/failure-and-loss.md)
- Report a per-element client config defect from diagnose as an `invalid_config` violation (HTTP 200), never an exception. Check: diagnose answers 200 on a malformed element config. [drafts-and-gates.md](../docs/principles/drafts-and-gates.md)
- Decode `layout` through `DraftLayoutDecoder`; never re-model the element in a DTO. Check: no DTO declares element fields. [drafts-and-gates.md](../docs/principles/drafts-and-gates.md)
- Keep preview and diagnose write-free. Check: neither route writes a repository; preview stores only its `ContentPreviewRequest`. [preview.md](../docs/principles/preview.md)
- Give every content-system route an `AdminApi/paths/content-system-<route>.json` entry, schemas only. Check: each GET and each preview, diagnose and mutation POST `_action` has one. [README.md](README.md#endpoint-reference)

## Where to look

- Controllers, request DTOs, response classes, the preview build's render mode and render caching, endpoint document index: [README.md](README.md#key-classes)
- How introspection, mutate, diagnose and preview chain: [workflow.md](docs/workflow.md)
- Preview route, token creation, `StoredElementCodec::decode()`, errors: [preview-url.md](docs/preview-url.md)
- Diagnose route, `rootSource` branch, errors: [diagnose.md](docs/diagnose.md)
- Diagnose response body, violation codes, per-type binding specification lookup: [diagnose-response.md](docs/diagnose-response.md)
- Draft mutation routes and request fields: [mutation.md](docs/mutation.md#request)
- Draft mutation response body: [mutation-response.md](docs/mutation-response.md)
- Draft mutation rejection conditions: [mutation-errors.md](docs/mutation-errors.md)
- Binding a specification through `bind-element` and `insert-element`: [mutation-binding.md](docs/mutation-binding.md)
- Persisted mutation routes, `expectedVersion` token, committed response: [persisted-mutation.md](docs/persisted-mutation.md#request)
- Persisted mutation rejection conditions: [persisted-mutation-errors.md](docs/persisted-mutation-errors.md)
- Shared draft-layout decode, style canonicalisation, element-local wiring codes: [draft-layout-decoder.md](docs/draft-layout-decoder.md)
