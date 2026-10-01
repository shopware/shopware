> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Uncacheable loader result disables page caching entirely via `RenderingCacheContext::disable()`
