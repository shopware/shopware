# Output

Post-hydration content transformation. Operates on fully hydrated ContentElement trees — read-only extraction and formatting, no database queries.

One exception sits ahead of hydration rather than after it: `ElementTreePruner`, and `PartialRenderer::pruneToTarget()` which drives it, run on the stored tree, before the lowering onto `ContentElement`.

## Response Formats

`ContentPage` itself is the full format: complete element trees with hydrated data. It provides lazy transformations to alternative formats:
- `getContentDecomposedPage()` → Skeletons + deduplicated data + assignments
- `getContentSkeletonPage()` → Element trees without hydrated data
- `getContentDataPage()` → Data + assignments without skeleton

Every route goes through a format-specific `AbstractResponseFactory` implementation, which takes the pipeline's `RenderResult` — the finished rendered forest, its layout reference, an optional resolved-value index, and, while the `ContentElement` bridge lives, the bridged `ContentPage` those factories still read. The factory answers two questions the route asks before rendering: `getRenderingMode()` and `collectsValueIndex()`.

## Partial Rendering

Extracts specific element subtree via `?elementId` parameter. `SubTreeExtractor` searches roots sequentially — first match returned. Same elementId in multiple roots returns only first occurrence. Pruning keeps context-dependent ancestors during hydration so data still flows correctly to the target; extraction then drops those ancestors and returns only the target subtree.

The two halves therefore sit on opposite sides of the lowering: `ElementTreePruner` rebuilds the kept path out of `StoredElement`s through `StoredElement::withSlots()` (so a field added to the element later rides across on its own), and reports a root that does not hold the target as `null` rather than as an error — the forest has other roots to try, and `PartialRenderer::extractTarget()` is the one place a genuinely absent target becomes `elementNotFound`.

Header and footer sources never resolve a target element, so those sections never support partial rendering.

## Subdirectories

- **Struct/** - Response data structures (ContentPage, ContentDecomposedPage, ContentSkeletonPage, ContentDataPage)
- **Format/** - Response factory implementations (Full, Decomposed, Skeleton, Data)
