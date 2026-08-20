# Output

Response formatting and encoding. Operates on the finished rendered forest (`Rendering/RenderedElement`) — read-only extraction and formatting, no database queries.

One exception sits ahead of the render step rather than after it: `ElementTreePruner`, and `PartialRenderer::pruneToTarget()` which drives it, run on the stored tree, before anything is rendered.

## Response Formats

Three formats write their own body out of the finished render, each through a page encoder of its own:
- **full** → `Encoder/ContentPageEncoder` walks the forest and writes page keys, element keys and property values
- **decomposed** → `Encoder/ContentDecomposedPageEncoder` projects the same node shape without property values, over the two maps of `Encoder/ResolvedValueIndexEncoder`
- **data** → `Encoder/ContentDataPageEncoder` writes `id`, `name` and `version` alongside those two maps, carrying no element structure — the half a client fetches once it already holds a cached skeleton

The decomposed and data formats are siblings over the same `Index/ResolvedValueIndex` rather than one derived from the other, which is why the `data`/`assignments` pair and the per-leaf protection gate over its values are encoded in the one place both read.

The fourth format keeps passing through the framework encoder as a plain struct:
- **skeleton** → `Format/SkeletonResponseFactory` projects the forest through `Struct/ContentSkeletonElement::fromRendered()`, keeping id, component, slots and style and dropping every property value

Every route goes through a format-specific `AbstractResponseFactory` implementation, which takes the pipeline's `RenderResult` — the finished rendered forest, its layout reference, an optional resolved-value index, and, while the `ContentElement` bridge lives, the bridged `ContentPage`. The factory answers two questions the route asks before rendering: `getRenderingMode()` and `collectsValueIndex()`. The three encoded formats hand the whole result to their route response, which exposes the bridged page to the framework as its struct and keeps the result for the encoder to read; the skeleton factory builds its `Struct/ContentSkeletonPage` on the spot and passes only that.

## Partial Rendering

Extracts specific element subtree via `?elementId` parameter. `SubTreeExtractor` searches roots sequentially — first match returned. Same elementId in multiple roots returns only first occurrence. Pruning keeps context-dependent ancestors through the render step so data still flows correctly to the target; extraction then drops those ancestors and returns only the target subtree.

The two halves therefore sit on opposite sides of the render step: `ElementTreePruner` rebuilds the kept path out of `StoredElement`s through `StoredElement::withSlots()` (so a field added to the element later rides across on its own), and reports a root that does not hold the target as `null` rather than as an error — the forest has other roots to try, and `PartialRenderer::extractTarget()` is the one place a genuinely absent target becomes `elementNotFound`.

Header and footer sources never resolve a target element, so those sections never support partial rendering.

## Subdirectories

- **Struct/** - Response data structures: `ContentPage`, the bridged page the encoded formats' responses still expose as their struct, and `ContentSkeletonPage` / `ContentSkeletonElement` for the skeleton format, plus `EncodedContentPage`, the carrier that hands an already-encoded body and the alias it reports to the framework's response encoding
- **Format/** - Response factory implementations (Full, Decomposed, Skeleton, Data)
- **Encoder/** - The module's own wire shape: `ContentPageEncoder`, `ContentDecomposedPageEncoder` and `ContentDataPageEncoder`, the `ResolvedValueIndexEncoder` the latter two share, and `ContentResponseEncodingListener`, which removes `includes`/`excludes` from every content response and swaps those three formats' responses for the carrier
- **Index/** - `ResolvedValueIndex` and its factory, the value model the decomposed and data formats are built on
