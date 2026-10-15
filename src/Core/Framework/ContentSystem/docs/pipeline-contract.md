# The Render Pipeline's Ordered Contract

What `ContentPipeline::load()` does, in the order it does it, and which steps judge which forest.

Specification resolution and layout-entity loading happen in `SalesChannel/ContentRoute`, **not** here. `RenderingSpecificationResolver` selects a source by iterating `supports()` and taking the first match — a bool check, not a null-return.

## The Order

`load()` receives a loaded `RenderableLayout` (stored elements) and runs:

1. `Event/ContentTreePreparationEvent`.
2. **Stored preparation** — `Layout/Scaffolding/StoredTreePreparer`: parse mapping-token spans from the actual tree returned by preparation listeners, then substitute placeholders in authored text while shifting those spans across changed literal segments. Tokens introduced by a placeholder are not parsed. Next wrap the forest with `Layout/Scaffolding/VirtualRootWrapper`, then partially prune it via `Output/PartialRenderer`. Preparation returns the tree, the pre-prune forest, scaffolding, and captured parse metadata in a `Layout/Scaffolding/TreePreparationResult`.
3. **Duplicate-element-id rejection**, over the result's pre-prune forest.
4. `Rendering/WiringPlanner::plan()` — wiring validation on that same pre-prune forest, then redistribute derivation on the pruned tree.
5. **The render step** — `Rendering/ElementLowering::lower()`: resolve each surviving element's loader requirements across the forest; resolve only consumed page-level requirements against the virtual root (ordinary root consumers, whole-field mapping sources, and recognized inline-token paths); deliver ordinary and mapped values; expand mapped text using the captured token spans; then mint the rendered tree. The virtual-root wrapper comes from the pre-prune forest, so a partial render resolves the same page requirements as the full render. `RenderingMode::FULL` runs these steps; `SKELETON` mints structure only.
6. **The finishing steps**, now on the rendered forest — `VirtualRootWrapper::unwrap()`, then `PartialRenderer::extractTarget()`, both driven by the `RenderScaffolding`.
7. `Event/RenderedTreeFinalizationEvent`, over the rendered forest, in both modes.
8. **Duplicate-element-id rejection again**, over the forest the event handed back.

It returns an `Output/RenderResult`: the finished rendered forest, the layout reference, and an optional `Output/Index/ResolvedValueIndex`. Both format answers come from the route — the `RenderingMode`, and whether the format collects a value index.

Output encoding and sanitization happen after `ContentPipeline::load()` returns. The Store API response encoder preserves mapped text as data; the Storefront `Sw:Content:Text` component passes its text through `sw_sanitize` before marking the output safe. Thus mapped markup follows the same purifier as authored rich text, while provider candidates remain responsible for offering only storefront-safe values.

Ordinary dotted root consumers deliver a present `null` when their ambient root was present but path resolution found no value. A missing ambient root delivers no key. Typed mappings use the live catalogue and omit their destination when the source, cardinality, or effective value is no longer valid. Inline expansion interprets tokens only in opted-in top-level string properties with a root source. It leaves mapping-looking text in other properties and attribute positions literal.

## Why Two Duplicate-Id Checks

The second is not redundant. A finalization listener may replace the forest, and the pre-lowering check at step 3 ran too early to see that. Both throw `DUPLICATE_ELEMENT_ID` (500, not a client defect) and both run in either mode.

## Why the Planner Is Split

Wiring validation and redistribute derivation are the two halves of `WiringPlanner::plan()`, deliberately separated by which forest they judge. Validation runs on the pre-prune forest, so a defect in a subtree a partial render discards still fails the render. Derivation runs on the surviving tree and throws nothing.
