# The Render Pipeline's Ordered Contract

What `ContentPipeline::load()` does, in the order it does it, and which steps judge which forest.

Specification resolution and layout-entity loading happen in `SalesChannel/ContentRoute`, **not** here. `RenderingSpecificationResolver` selects a source by iterating `supports()` and taking the first match — a bool check, not a null-return.

## The Order

`load()` receives a loaded `RenderableLayout` (stored elements) and runs:

1. `Event/ContentTreePreparationEvent`.
2. **Stored preparation** — `Layout/Scaffolding/StoredTreePreparer`: placeholder resolution, then the virtual-root wrap via `Layout/Scaffolding/VirtualRootWrapper`, then the partial prune via `Output/PartialRenderer`. All of it still on stored elements, returning a `Layout/Scaffolding/TreePreparationResult`.
3. **Duplicate-element-id rejection**, over the result's pre-prune forest.
4. `Rendering/WiringPlanner::plan()` — wiring validation on that same pre-prune forest, then redistribute derivation on the pruned tree.
5. **The render step** — `Rendering/ElementLowering::lower()`: forest-wide data resolution, the once-per-render root-ambient resolution of the specification's page-level data requirements against the virtual-root wrapper the pipeline hands it from the pre-prune forest, context-delivery resolution, and rendered-tree minting. `RenderingMode::FULL` does all four; `SKELETON` mints structure only.
6. **The finishing steps**, now on the rendered forest — `VirtualRootWrapper::unwrap()`, then `PartialRenderer::extractTarget()`, both driven by the `RenderScaffolding`.
7. `Event/RenderedTreeFinalizationEvent`, over the rendered forest, in both modes.
8. **Duplicate-element-id rejection again**, over the forest the event handed back.

It returns an `Output/RenderResult`: the finished rendered forest, the layout reference, and an optional `Output/Index/ResolvedValueIndex`. Both format answers come from the route — the `RenderingMode`, and whether the format collects a value index.

## Why Two Duplicate-Id Checks

The second is not redundant. A finalization listener may replace the forest, and the pre-lowering check at step 3 ran too early to see that. Both throw `DUPLICATE_ELEMENT_ID` (500, not a client defect) and both run in either mode.

## Why the Planner Is Split

Wiring validation and redistribute derivation are the two halves of `WiringPlanner::plan()`, deliberately separated by which forest they judge. Validation runs on the pre-prune forest, so a defect in a subtree a partial render discards still fails the render. Derivation runs on the surviving tree and throws nothing.
