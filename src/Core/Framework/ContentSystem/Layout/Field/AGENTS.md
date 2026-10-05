## Navigation

- Why conformance runs on every DAL write, skip state included: [write-admission.md](../../docs/principles/write-admission.md)

## Constraints

- `StoredElementListFieldSerializer::buildConstraints` returns `Layout/Codec/StoredTreeConstraints::build()` and appends only a `NotBlank` for the field's own `Required` flag. The descriptor already covers the whole forest including its own `All()`, so nothing wraps it; its `getCachedConstraints` override skips the inherited process-wide cache, because the descriptor's style part reads the runtime-mutable style option registry per call
- Infrastructure only — used in `ContentLayoutDefinition`, not domain API
- Keep conformance independent of `LayoutGate::SKIP_VALIDATION_STATE`: `StoredElementListFieldSerializer`, `LayoutWriteBoundary` and the `StoredTreeConstraints` descriptor never read it.
