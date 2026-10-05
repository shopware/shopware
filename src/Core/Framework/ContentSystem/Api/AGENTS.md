## Navigation

- Why nothing is dropped silently, and how a defect is classified: [failure-and-loss.md](../docs/principles/failure-and-loss.md)
- Preview action route, token creation, decode gate, token contract: [docs/preview-url.md](docs/preview-url.md)
- Diagnose action route, contract, `rootSource` resolvability branch: [docs/diagnose.md](docs/diagnose.md)
- Stateless draft mutation actions, routes and contract: [docs/mutation.md](docs/mutation.md)
- Persisted mutation actions, routes and contract: [docs/persisted-mutation.md](docs/persisted-mutation.md)
- Shared draft-layout decode paths and style canonicalisation: [docs/draft-layout-decoder.md](docs/draft-layout-decoder.md)
- Mutation response wire shape and encoding: [docs/mutation-response.md](docs/mutation-response.md)
- Diagnose response wire shape and encoding: [docs/diagnose-response.md](docs/diagnose-response.md)

## Constraints

- Bind each action's request DTO via `#[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]` on the action parameter, never the DTO class; `layoutId` stays a route path argument, not a DTO field.
- Draft-route and persisted-route failures are `ContentSystemException`s (400 / 404 / 409); condition tables: [docs/mutation-errors.md](docs/mutation-errors.md), [docs/persisted-mutation-errors.md](docs/persisted-mutation-errors.md), [docs/diagnose.md](docs/diagnose.md#errors), [docs/preview-url.md](docs/preview-url.md#errors)
- Mutation responses (draft and persisted) share one shape and never silently drop edited-out content. Shape/encoding: [docs/mutation-response.md](docs/mutation-response.md)
- Diagnose reports per-element client config defects as `invalid_config` violations (HTTP 200), not errors — see `ContentSystemException::isClientDefect()`
- The element-local wiring codes (`PROPERTY_ALIAS_COLLISION`, `REDISTRIBUTE_DOTTED_PATH`, `REDISTRIBUTE_CONFLICT`) are client defects — see [docs/draft-layout-decoder.md](docs/draft-layout-decoder.md)
- `layout` is decoded via the shared `Api/DraftLayoutDecoder` — do NOT re-model the element in the DTO
- Preview: no persistence, no render caching (empty `RenderingCacheContext`, `RenderingMode::FULL`); only the draft envelope is cached, by `ContentPreviewPayloadStore`. Diagnose: no persistence, no rendering — draft tree only
- Deriving a client's applicable binding specifications for an element is a per-type catalog lookup, not a resolution against the element's actual wiring — see [docs/diagnose-response.md](docs/diagnose-response.md)
- OpenAPI: every content-system route carries an `AdminApi/paths/` entry — the introspection GET routes and every preview/diagnose/draft-mutation/persisted-mutation POST `_action` route each have one, named `content-system-<route>.json`. Schemas only; the human contract is in [README.md](README.md#endpoint-reference)
