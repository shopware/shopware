## Constraints

- Load no data, query no database and write no element property in `Output/`, which runs after the render step. The partial prune before it (`ElementTreePruner`, `PartialRenderer::pruneToTarget()`) follows the same rule. Check: does the diff add a load, a query or a property write under `Output/`? See [README.md](README.md#output).
- Encode the full, decomposed and data bodies only in `ContentResponseEncodingListener`, hand every `Struct` leaf to `StructEncoder::encode()`, and give in-process consumers `ContentPage`, never the carrier. Check: `ContentPageEncoderTest` pins the per-leaf delegation. See [wire-contract.md](../docs/principles/wire-contract.md#the-module-encodes-its-responses).
- Keep the skeleton a plain `Struct` through `StructEncoder`, built only through `ContentSkeletonElement::fromRendered()`. Check: does the diff give `ContentSkeletonElement` an entity payload or a second construction path? See [wire-contract.md](../docs/principles/wire-contract.md#the-module-encodes-its-responses).
- Build every format from the one `RenderResult` forest: no format resolves data or walks the stored tree on its own. Check: `ContentRouteRenderingTest` pins the cross-format structure. See [wire-contract.md](../docs/principles/wire-contract.md#every-response-format-is-a-structural-projection-of-one-rendered-forest).
- Say so in any change that moves a content response body: PHP names behind a module encoder do not change the JSON. Land a wire deletion in the same commit as its replacement, and never let one captured body both prove byte identity and record a deliberate wire change. Check: does the diff change a body `ContentRouteRenderingTest` asserts? See [wire-contract.md](../docs/principles/wire-contract.md#a-php-name-never-sets-the-wire-key-behind-a-module-owned-encoder).

## Where to look

- Response formats, the encoder behind each, the two wire maps of the decomposed and data bodies, the skeleton format, and which PHP instance a ref carries: [README.md](README.md#response-formats)
- Partial rendering by `?elementId`, the pruner and extractor on either side of the render step, and multi-root search: [README.md](README.md#partial-rendering)
- What `Struct/`, `Format/`, `Encoder/` and `Index/` hold, and where `ContentResponseEncodingListener` sits and how it selects a response: [README.md](README.md#subdirectories)
- Why `includes` and `excludes` never reach a content response, on the store-api routes and on the preview URL: [SalesChannel/README.md](../SalesChannel/README.md#endpoints), [Api/docs/preview-url.md](../Api/docs/preview-url.md#errors)
- What `RenderingMode` gates inside the render step (data and context resolution; the render runs in both modes): [rendering.md](../docs/principles/rendering.md#structure-is-a-function-independent-of-rendering-mode), [service-tags-and-types.md](../docs/service-tags-and-types.md#type-reference)
- Why a render result without an index throws `resolvedValueIndexMissing`: [failure-and-loss.md](../docs/principles/failure-and-loss.md#a-component-throws-where-it-meets-invalid-data-and-nothing-degrades-silently)
