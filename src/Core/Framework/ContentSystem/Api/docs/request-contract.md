# Request Envelopes and Draft Decoding

How a controller action receives its payload, and how the layout tree inside it is decoded before any operation runs.

## Envelope DTOs

Every action binds its own DTO with `#[MapRequestPayload(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]` on the **action parameter** — the attribute sits on the parameter, never on the DTO class. The DTOs are envelopes: they carry the operation's parameters and the raw `layout`, and deliberately do not re-model the element (see [Decoding](#decoding) below).

- **Preview and diagnose**: `ContentPreviewRequest`, `ContentDiagnoseRequest`.
- **Draft mutation**: `InsertElementRequest`, `RemoveElementRequest`, `MoveElementRequest`, `ReplaceElementRequest`, `DuplicateElementRequest`, `WrapElementsRequest`, `UnwrapElementRequest`, `AttachElementRequest`, `InsertPresetRequest`, `BindElementRequest`. Each carries its operation parameters, the raw `layout`, and an optional `rootSource`.
- **Persisted mutation**: `ContentLayoutInsertRequest`, `ContentLayoutRemoveRequest`, `ContentLayoutMoveRequest`, `ContentLayoutReplaceRequest`, `ContentLayoutDuplicateRequest`, `ContentLayoutWrapElementsRequest`, `ContentLayoutUnwrapRequest`, `ContentLayoutAttachRequest`, `ContentLayoutBindRequest`. Each carries its operation parameters plus a required-but-nullable `expectedVersion` (the layout's `updatedAt`; `null` matches a never-updated layout). They have no `layout` — the tree is loaded from storage — and no `rootSource`, which comes from the loaded layout. `layoutId` is the route path argument, not a DTO field.

Field-level specifics:

- `WrapElementsRequest` / `ContentLayoutWrapElementsRequest` constrain `$elementIds` with `#[Assert\Type('array')]`, `#[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]` and `#[Assert\Unique]`.
- The attach DTOs carry a raw `element`, the subtree to splice in.
- `BindElementRequest` / `ContentLayoutBindRequest` carry `elementId` plus a source-qualified `bindingSpecificationId` (`source:id`) in place of the other DTOs' operation fields.
- `InsertElementRequest` / `ContentLayoutInsertRequest` additionally carry an optional trailing `bindingSpecificationId`, applied onto the inserted element atomically. Both stay `ALLOW_EXTRA_ATTRIBUTES => false`.

## Decoding

`DraftLayoutDecoder` is the one decode path for a request-supplied tree, injected into the preview, diagnose and both mutation controllers. It runs a structural pre-decode gate, then `Layout/Codec/StoredElementCodec::decode()` per element, then the write boundary's style canonicalisation.

Style is normalized through the same `Layout/StoredTreeStyleNormalizer` service that `Layout/LayoutWriteBoundary` runs, on the strict and the lenient path alike, so a draft previews and diagnoses with the style shape its saved layout will carry. **Style only**: the boundary's default seeding and attribution reconciliation stay write-only by design, so a draft still shows unreconciled attribution and still over-reports an unseeded required default.

Three entry points:

- `decode()` — strict. A malformed or config-defective element fails the request with `invalidLayoutStructure` (400).
- `decodeOne()` — the same gate over a single element, used for the attach subtree.
- `decodeLintable()` — returns `[tree, list<Violation>]`, collecting client config defects as `invalid_config` violations for the diagnose route.

Storage-side decoding of the persisted `content_layout` column is a separate path: `Layout/Field/StoredElementListFieldSerializer` via `Layout/Codec/StoredTreeCodec`. The write gate decodes nothing itself; it reads the tree that serializer memoized.
