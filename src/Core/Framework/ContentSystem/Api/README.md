# Api

Admin API controllers for the content system. Store API rendering lives in `SalesChannel/`. The Admin-scoped (`ApiRouteScope`) endpoints: layout preview, resolve-and-diagnose, the stateless draft mutation actions, and persisted mutation actions that commit one edit to a stored layout.

## Key Classes

- `ContentPreviewController` - One action, binding `ContentPreviewRequest` via `#[MapRequestPayload]`, delegating orchestration to `ContentPreviewPageBuilder`. Mints a short-lived, openable preview URL for an unsaved draft layout; there is no direct render route, so checking well-formedness or resolvability without minting a token goes through the diagnose route instead. Route, mint mechanics, and the decode gate: [docs/preview-url.md](docs/preview-url.md). See [Preview is a second entry into the one rendering path, never a second storage path](../docs/principles/preview.md#preview-is-a-second-entry-into-the-one-rendering-path-never-a-second-storage-path).
- `ContentPreviewPageBuilder` - Builds the page for a `ContentPreviewRequest` against the assignment-free `RenderingSpecification`, in `RenderingMode::FULL` without value-index collection.
- `ContentPreviewPayloadStore` - Holds a serialized `ContentPreviewRequest` envelope under a 32-character token in the application cache for five minutes, never in the database; both directions, with their faults: [docs/preview-url.md](docs/preview-url.md).
- `ContentPreviewRequest` - envelope DTO bound via `#[MapRequestPayload]` on the action parameter. Fields: [docs/preview-url.md](docs/preview-url.md#request). The `layout` stays raw so `DraftLayoutDecoder` remains the single decode path.
- `ContentDiagnoseController` - Single `POST /api/_action/content-system/layout/diagnose` action. Decodes the draft layout via `DraftLayoutDecoder` (lenient: config defects surface as `invalid_config` diagnostics, not request failures); never reads or writes the stored `content_layout` entity. Optionally binds `rootSource` via `Adapter/RootSourceRegistry::resolveGated()` (the same path the draft mutation routes use, distinct from the preview action's assignment-free `resolveWithoutLayout()`) and returns a `DiagnoseResponse` with per-element resolutions plus a well-formedness/resolvability report. No persistence, no rendering.
- `ContentDiagnoseRequest` - envelope DTO bound via `#[MapRequestPayload]` on the action parameter. Without `rootSource`, only intrinsic well-formedness is evaluated.
- `DraftLayoutDecoder` - Shared draft-layout decode (structural pre-decode gate plus `Layout/Codec/StoredElementCodec::decode()`) used by preview, diagnose, and mutation routes. `decode()` is strict (a malformed/config-defective element is a 400 `invalidLayoutStructure`); `decodeLintable()` is lenient (config defects become `invalid_config` violations, the rest survives) for the diagnose route.
- `LayoutMutationController` - The stateless draft mutation actions, one per `Mutation/Op` operation, run through `Mutation/MutationPipeline`. Each returns a `MutationResponse` without persisting. Routes/contract: [docs/mutation.md](docs/mutation.md); rejection codes: [docs/mutation-errors.md](docs/mutation-errors.md).
- `ContentLayoutMutationController` - The persisted counterpart: commits one edit to a stored layout via `Mutation/PersistedLayoutMutator`, through the resolvability gates. The persisted routes add their own failure conditions (missing layout, version conflict). Routes/contract: [docs/persisted-mutation.md](docs/persisted-mutation.md); errors: [docs/persisted-mutation-errors.md](docs/persisted-mutation-errors.md).
- Draft mutation request DTOs - One per action, each bound via `#[MapRequestPayload]`, carrying the raw `layout`, the operation's parameters, and optional `rootSource`. Class names/field shape: [docs/mutation.md](docs/mutation.md#request).
- Persisted mutation request DTOs - One per action, each bound via `#[MapRequestPayload]`, carrying the operation's parameters plus a required-but-nullable `expectedVersion`; no `layout` field (loaded from storage), no `rootSource` field. Class names/field shape: [docs/persisted-mutation.md](docs/persisted-mutation.md#request).
- `LayoutDiagnosticsResultNormalizer` - Zero-dependency normalizer for the diagnostics half of an admin content response (resolutions map, diagnostics report). Invoked inside `MutationResponse`/`DiagnoseResponse` factories so the diagnostics half of every admin content response shares one wire shape.
- `MutationResponse` - `\JsonSerializable` owning the mutation response wire shape for every draft and persisted mutation route. Class shape/field-by-field encoding: [docs/mutation-response.md](docs/mutation-response.md).
- `DiagnoseResponse` - Sibling `\JsonSerializable` owning the resolve-and-diagnose wire shape (`resolutions`, `diagnostics`). Class shape/encoding: [docs/diagnose-response.md](docs/diagnose-response.md).

## Endpoint Reference

- [docs/workflow.md](docs/workflow.md) - How introspection, mutation, diagnose, and preview chain together while a client builds a layout.
- [docs/draft-layout-decoder.md](docs/draft-layout-decoder.md) - The shared request draft-layout decode path used by the preview, diagnose, and both mutation routes.
- [docs/preview-url.md](docs/preview-url.md) - The preview action that mints a short-lived, openable URL for a draft layout: route, request envelope, response, and error model.
- [docs/diagnose.md](docs/diagnose.md) - The resolve-and-diagnose action: route, request envelope, and error model.
- [docs/diagnose-response.md](docs/diagnose-response.md) - The diagnose response body: resolutions, diagnostics, and the violation codes.
- [docs/mutation.md](docs/mutation.md) - The stateless draft mutation actions and the request envelope they share.
- [docs/mutation-response.md](docs/mutation-response.md) - The response body every stateless mutation action returns.
- [docs/mutation-errors.md](docs/mutation-errors.md) - The failure conditions that abort a stateless mutation instead of being reported in diagnostics.
- [docs/mutation-binding.md](docs/mutation-binding.md) - Applying a binding specification through `bind-element` or `insert-element`, and the automatic default the scaffold applies.
- [docs/persisted-mutation.md](docs/persisted-mutation.md) - The persisted mutation actions, their request envelope, and their committed response.
- [docs/persisted-mutation-errors.md](docs/persisted-mutation-errors.md) - The failure conditions specific to the persisted mutation actions.

The introspection endpoints that feed these are documented with the systems they describe: element types in [../Layout/Type/docs/introspection.md](../Layout/Type/docs/introspection.md), style options in [../Layout/Element/Style/docs/introspection.md](../Layout/Element/Style/docs/introspection.md), data loaders in [../Hydration/DataLoader/docs/introspection.md](../Hydration/DataLoader/docs/introspection.md), and assignable entity types in [../Adapter/docs/introspection.md](../Adapter/docs/introspection.md).
