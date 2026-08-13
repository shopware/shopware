> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Runs AFTER hydration — MUST NOT load data, query database, or modify element properties
- Multi-root partial: searches sequentially, returns first match only
- `ContentDecomposedPage` is about response format (structure/data separation), NOT partial rendering (`?elementId`)
- `RenderingMode` determines if hydration runs: FULL (hydrate) vs SKELETON (structure only)
