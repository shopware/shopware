> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `StoredElementListFieldSerializer::buildConstraints` returns `Layout/Codec/StoredTreeConstraints::build()` and appends only a `NotBlank` for the field's own `Required` flag. The descriptor already covers the whole forest including its own `All()`, so nothing wraps it; its `getCachedConstraints` override skips the inherited process-wide cache, because the descriptor's style part reads the runtime-mutable style option registry per call
- Infrastructure only — used in `ContentLayoutDefinition`, not domain API
