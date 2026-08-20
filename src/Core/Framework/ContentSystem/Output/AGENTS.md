> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Runs AFTER the render step — MUST NOT load data, query database, or modify element properties. The one exception is the partial prune (`ElementTreePruner`, `PartialRenderer::pruneToTarget()`), which runs before the render step on `StoredElement`s; the same no-loading rule holds there
- `PartialRenderer` straddles the render step: `pruneToTarget()` takes and returns `list<StoredElement>` before it, `extractTarget()` takes `list<RenderedElement>` and returns a `RenderedElement` after it. `SubTreeExtractor` is rendered-side too, and returns the found instance rather than a copy — `RenderedElement` is `final readonly`, so nothing can mutate what the rest of the tree still points at
- `ElementTreePruner::pruneToPathAndDescendants()` returns `null` for a root that does not hold the target — not an error, because the caller has other roots to try; `extractTarget()` raises `elementNotFound` once the forest is exhausted
- The pruner rebuilds kept ancestors through `StoredElement::withSlots()`, never through the constructor — the same idiom `Layout/StoredTree`'s surgery uses
- Multi-root partial: searches sequentially, returns first match only
- `ContentDecomposedPage` is about response format (structure/data separation), NOT partial rendering (`?elementId`)
- `RenderingMode` determines whether data and context are resolved inside the render step, which runs either way: FULL resolves both, SKELETON mints structure only
