@README.md

## Constraints

- Runs AFTER hydration — MUST NOT load data, query database, or modify element properties
- Multi-root partial: searches sequentially, returns first match only
- `ContentDecomposedPage` is about response format (structure/data separation), NOT partial rendering (`?elementId`)
- `RenderingMode` determines if hydration runs: FULL (hydrate) vs SKELETON (structure only)
