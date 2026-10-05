# Output

Response formatting and encoding. Operates on the finished rendered forest (`Rendering/RenderedElement`) — read-only extraction and formatting, no database queries.

One exception sits ahead of the render step rather than after it: `ElementTreePruner`, and `PartialRenderer::pruneToTarget()` which drives it, run on the stored tree, before anything is rendered.

## Response Formats

Three formats write their own body out of the finished render, each through a page encoder of its own:
- **full** → `Encoder/ContentPageEncoder` walks the forest and writes the page keys (`id`, `name`, `version`, `elements`), every element's keys (`id`, `component`, `properties`, `slots` and `style` omitted when empty, `apiAlias` last, on every node at every depth) and the property values
- **decomposed** → `Encoder/ContentDecomposedPageEncoder` projects the same node shape without property values, repeating the `content_skeleton_element` alias as its own constant rather than building `ContentSkeletonElement`, over the two maps of `Encoder/ResolvedValueIndexEncoder` (`data`: ref → value; `assignments`: element id → property key → ref)
- **data** → `Encoder/ContentDataPageEncoder` writes `id`, `name` and `version` alongside those two maps, carrying no element structure — the half a client fetches once it already holds a cached skeleton

The decomposed and data formats are siblings over the same `Index/ResolvedValueIndex` rather than one derived from the other.

Which PHP instance a ref ends up carrying follows from which of `Index/ResolvedValueIndexFactory`'s lookups deduped it. A ref carries the instance present when it was created. A later loader-resolved key deduped through the loader-identity map reuses that ref by identity key alone, without comparing values, so the wire serves the first element's instance whether or not the second load produced the same object. Why the key skips a value comparison: [Also true by construction](../docs/principles/data-loading.md#also-true-by-construction). A key deduped through the instance map is the same instance by construction, which is what makes a broadcast delivery share its provider's ref. The value map never sees an object at all: it runs only for non-object non-null values, compared by `StoredValue::equals()` semantics. Every explicit null shares one ref regardless of origin.

The fourth format keeps passing through the framework encoder as a plain struct:
- **skeleton** → `Format/SkeletonResponseFactory` projects the forest through `Struct/ContentSkeletonElement::fromRendered()`, keeping id, component, slots and style and dropping every property value

Every route goes through a format-specific `AbstractResponseFactory` implementation, which takes the pipeline's `RenderResult` (the finished rendered forest, its layout reference, and an optional resolved-value index). The three encoded formats hand the whole result to their route response, which builds a `Struct/ContentPage` off it to hand the framework as its struct and keeps the result behind `getRenderResult()` for the encoder to read. The skeleton factory builds its `Struct/ContentSkeletonPage` on the spot and passes only that.

## Partial Rendering

Extracts specific element subtree via `?elementId` parameter. `SubTreeExtractor` searches roots sequentially — first match returned. Same elementId in multiple roots returns only first occurrence. Pruning keeps context-dependent ancestors through the render step so data still flows correctly to the target; extraction then drops those ancestors and returns only the target subtree.

The pruner and the extractor therefore sit on opposite sides of the render step: `ElementTreePruner` rebuilds the kept path out of `StoredElement`s through `StoredElement::withSlots()`, never through the constructor, the same idiom as `Layout/StoredTree`'s surgery, and reports a root that does not hold the target as `null` rather than as an error, because the forest has other roots to try. `PartialRenderer::extractTarget()` is the one place a genuinely absent target becomes `elementNotFound`.

Header and footer sources never resolve a target element, so those sections never support partial rendering.

Partial rendering is independent of the response format. `SubTreeExtractor` returns the found instance rather than a copy: `RenderedElement` is `final readonly`, so nothing can mutate what the rest of the tree still points at.

## Subdirectories

- **Struct/** - Response data structures: `ContentPage`, the page the encoded formats' responses build off the render result and expose as their struct, and `ContentSkeletonPage` / `ContentSkeletonElement` for the skeleton format, plus `EncodedContentPage`, the carrier that hands an already-encoded body and the alias it reports to the framework's response encoding
- **Format/** - Response factory implementations (Full, Decomposed, Skeleton, Data)
- **Encoder/** - The module's own wire shape: `ContentPageEncoder`, `ContentDecomposedPageEncoder` and `ContentDataPageEncoder`, the `ResolvedValueIndexEncoder` the latter two share, and `ContentResponseEncodingListener` (`kernel.response`, between `StoreApiSeoResolver` and `StoreApiResponseListener`), which swaps those three formats' responses for the carrier through an explicit `match` over the three concrete `SalesChannel/AbstractContentRouteResponse` classes, not a common encoder interface
- **Index/** - `ResolvedValueIndex` and its factory, the value model the decomposed and data formats are built on
