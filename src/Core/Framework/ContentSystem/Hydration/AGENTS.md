> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Phase 1 (data loading) MUST complete before Phase 2 (context resolution)
- Requirement key determines property storage: `$element->setProperty($key, $result->data)`
- `hydrate()` returns `Generator<ContentElement>` — caller converts via `iterator_to_array()`
- Uncacheable loader result disables page caching entirely via `RenderingCacheContext::disable()`
