# Draft-Layout Decode

The shared request draft-layout decode path: a structural pre-decode gate plus per-element `Layout/Codec/StoredElementCodec::decode()` and the write boundary's style canonicalisation. Injected into the preview, diagnose, and both mutation controllers — [preview-url.md](preview-url.md), [diagnose.md](diagnose.md), [mutation.md](mutation.md), [persisted-mutation.md](persisted-mutation.md).

Every decoded element's `style` is normalized through the same `Layout/StoredTreeStyleNormalizer` service `Layout/LayoutWriteBoundary` runs, on the strict and the lenient path alike. See [Draft and persisted paths decode and check through the same components](../../docs/principles/drafts-and-gates.md#draft-and-persisted-paths-decode-and-check-through-the-same-components).

`decode()` is strict (a malformed or config-defective element is a 400 `invalidLayoutStructure`); `decodeOne()` runs the same gate on one element (the attach subtree); `decodeLintable()` collects client config defects as `invalid_config` violations for the diagnose route.

Storage-side decode of the persisted `content_layout` column stays separate, in `Layout/Field/StoredElementListFieldSerializer` via `Layout/Codec/StoredTreeCodec`; the write gate reads the tree that serializer memoized.

The element-local wiring codes `PROPERTY_ALIAS_COLLISION`, `REDISTRIBUTE_DOTTED_PATH`, and `REDISTRIBUTE_CONFLICT` are raised by `Layout/Codec/StoredElementCodec::decode()`. `decode()` aggregates them into the 400 `invalidLayoutStructure`; `decodeLintable()` collects each as `invalid_config` instead, attributed to the root element id from the pre-decode gate, and drops the root from the returned tree.
