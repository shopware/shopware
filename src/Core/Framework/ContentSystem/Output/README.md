# Output

Response formatting and encoding. Operates on the finished rendered forest (`Rendering/RenderedElement`) — read-only extraction and formatting, no database queries.

One exception sits ahead of the render step rather than after it: `ElementTreePruner`, and `PartialRenderer::pruneToTarget()` which drives it, run on the stored tree, before anything is rendered.

## Response Formats

Two formats read the rendered forest directly:
- **full** → `Encoder/ContentPageEncoder` walks it and writes the body itself
- **skeleton** → `Format/SkeletonResponseFactory` projects it through `Struct/ContentSkeletonElement::fromRendered()`, keeping id, component, slots and style and dropping every property value

The remaining two are still assembled from the bridged `ContentPage`, which provides lazy transformations to them:
- `getContentDecomposedPage()` → Skeletons + deduplicated data + assignments
- `getContentDataPage()` → Data + assignments without skeleton

Every route goes through a format-specific `AbstractResponseFactory` implementation, which takes the pipeline's `RenderResult` — the finished rendered forest, its layout reference, an optional resolved-value index, and, while the `ContentElement` bridge lives, the bridged `ContentPage` those factories still read. The factory answers two questions the route asks before rendering: `getRenderingMode()` and `collectsValueIndex()`.

## Partial Rendering

Extracts specific element subtree via `?elementId` parameter. `SubTreeExtractor` searches roots sequentially — first match returned. Same elementId in multiple roots returns only first occurrence. Pruning keeps context-dependent ancestors through the render step so data still flows correctly to the target; extraction then drops those ancestors and returns only the target subtree.

The two halves therefore sit on opposite sides of the render step: `ElementTreePruner` rebuilds the kept path out of `StoredElement`s through `StoredElement::withSlots()` (so a field added to the element later rides across on its own), and reports a root that does not hold the target as `null` rather than as an error — the forest has other roots to try, and `PartialRenderer::extractTarget()` is the one place a genuinely absent target becomes `elementNotFound`.

Header and footer sources never resolve a target element, so those sections never support partial rendering.

## Subdirectories

- **Struct/** - Response data structures (ContentPage, ContentDecomposedPage, ContentSkeletonPage, ContentDataPage) plus `EncodedContentPage`, the carrier that hands an already-encoded body to the framework's response encoding
- **Format/** - Response factory implementations (Full, Decomposed, Skeleton, Data)
- **Encoder/** - The module's own wire shape: `ContentPageEncoder` for the full format, and `ContentResponseEncodingListener`, which removes `includes`/`excludes` from every content response and swaps the full format's response for the carrier
- **Index/** - `ResolvedValueIndex` and its factory, the value model the decomposed and data formats will be rebuilt on
