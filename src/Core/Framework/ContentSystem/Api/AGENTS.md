> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Source Code References

- `ContentPreviewController` - One route, mints a token-addressed preview URL; delegates orchestration to `ContentPreviewPageBuilder`
- `ContentPreviewPayloadStore` - `store()` / `load()` for the token-addressed preview envelope, cached for five minutes
- `ContentDiagnoseController` - Resolve-and-diagnose over a request draft; never reads or writes the stored `content_layout` entity
- `LayoutMutationController` - Ten draft routes, one per `Mutation/Op`, each returning `MutationResponse` without persisting
- `ContentLayoutMutationController` - The persisted counterpart: nine routes that delegate to `Mutation/PersistedLayoutMutator::mutate()`
- Request DTOs - One envelope per action, bound with `#[MapRequestPayload]` on the action parameter
- `DraftLayoutDecoder` - The one decode path for a request-supplied tree: `decode()`, `decodeOne()`, `decodeLintable()`
- `MutationResponse` / `DiagnoseResponse` - `\JsonSerializable`, private constructor plus one factory; output-only, never cached or denormalized
- `LayoutDiagnosticsResultNormalizer` - Zero-dependency normalizer both response factories instantiate, so diagnostics share one wire shape
- `DraftLayoutChecker` (module root) - The preview action's draft check, an intrinsic-subset diagnostics run

## Constraints

- `layout` is decoded via the shared `Api/DraftLayoutDecoder` — do NOT re-model the element in the DTO
- Preview: no persistence, no caching — empty `RenderingCacheContext`, `RenderingMode::FULL`. Diagnose: no persistence, no rendering
- Diagnose reports per-element client config defects as `invalid_config` violations in the body (HTTP 200), not as errors; only non-client-defect faults propagate (`ContentSystemException::isClientDefect()`)
- The three element-local wiring codes `PROPERTY_ALIAS_COLLISION` / `REDISTRIBUTE_DOTTED_PATH` / `REDISTRIBUTE_CONFLICT` are client defects raised by `Layout/Codec/StoredElementCodec::decode()`; `decode()` aggregates them into a 400, `decodeLintable()` collects each as `invalid_config` attributed to the root element id and drops that root from the tree
- A client derives the specifications applicable to an element from `bindingSpecifications[element.component]` on that element's type entry in `content-system-element-types.json` — a per-type catalog lookup, not a resolution against the element's wiring or ancestry
- Every content-system route carries an `AdminApi/paths/` entry; see [docs/routes.md](docs/routes.md)

## Navigation

- [docs/routes.md](docs/routes.md) - every route, its name, and its OpenAPI path file
- [docs/request-contract.md](docs/request-contract.md) - the envelope DTOs and how a draft tree is decoded
- [docs/preview-url.md](docs/preview-url.md) - the preview URL contract and mint-time admission
- [docs/preview-internals.md](docs/preview-internals.md) - the shared page builder and the payload store
- [docs/diagnose.md](docs/diagnose.md) - the resolve-and-diagnose route
- [docs/diagnose-response.md](docs/diagnose-response.md) - its response shape
- [docs/mutation.md](docs/mutation.md) - the draft mutation routes
- [docs/mutation-response.md](docs/mutation-response.md) - the seven-key response shape
- [docs/mutation-errors.md](docs/mutation-errors.md) - the draft failure set
- [docs/mutation-binding.md](docs/mutation-binding.md) - applying a binding specification through a route
- [docs/persisted-mutation.md](docs/persisted-mutation.md) - the persisted routes and their version guard
- [docs/persisted-mutation-errors.md](docs/persisted-mutation-errors.md) - their additional failures
- [docs/workflow.md](docs/workflow.md) - how the routes compose into an editing session
- [README.md](README.md) - the mental model and the endpoint reference
