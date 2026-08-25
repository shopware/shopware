> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Loaders never read the element: `load()` receives `LoaderInputs` and the requirement, never the `StoredElement` — inputs are resolved and type-checked before the call, in the render step. See [../Rendering/AGENTS.md](../Rendering/AGENTS.md)
- Uncacheable loader result disables page caching entirely via `RenderingCacheContext::disable()`
