# Pipeline Steps

The data-flow diagram is in [data-flow.md](data-flow.md). Specification resolution and layout-entity loading happen in `SalesChannel/ContentRoute`, before the pipeline.

`load()` receives a loaded `RenderableLayout` (stored elements) and runs:

1. `Event/ContentTreePreparationEvent`.
2. **Stored preparation** — `Layout/Scaffolding/StoredTreePreparer`: in FULL mode parse mapping-token spans from the actual tree returned by preparation listeners, then substitute placeholders in authored text while shifting those spans across changed literal segments. Tokens introduced by a placeholder are not parsed. Next wrap the forest with `Layout/Scaffolding/VirtualRootWrapper`, then partially prune it via `Output/PartialRenderer`. Preparation returns the tree, the pre-prune forest, scaffolding, and captured parse metadata in a `Layout/Scaffolding/TreePreparationResult`.
3. **Duplicate-element-id rejection**, over the result's pre-prune forest.
4. `Rendering/WiringPlanner::plan()` — wiring validation on that same pre-prune forest, then redistribute derivation on the pruned tree.
5. **The render step** — `Rendering/ElementLowering::lower()`: resolve each surviving element's loader requirements across the forest; resolve only consumed page-level requirements against the virtual root (ordinary root consumers, whole-field mapping sources, and recognized inline-token paths); expand mapped text using the captured token spans; deliver ordinary and mapped values using that expanded forest, including provider text and child keys used for keyed selection; then mint the rendered tree. Expansion does not change the original stored layout or reparse substituted text. The virtual-root wrapper comes from the pre-prune forest, so a partial render resolves the same page requirements as the full render. `RenderingMode::FULL` runs these steps; `SKELETON` mints structure only.
6. **The finishing steps**, now on the rendered forest — `VirtualRootWrapper::unwrap()`, then `PartialRenderer::extractTarget()`, both driven by the `RenderScaffolding`.
7. `Event/RenderedTreeFinalizationEvent`, over the rendered forest, in both modes. Product-domain enrichment loads variant-selection options for the product that won mapping/fallback delivery, using the existing configurator service. It changes property values only, adds the selected product and parent cache dependencies, and does nothing for skeletons. The ordinary forest/page loader phases above retain their order.
8. **Duplicate-element-id rejection again**, over the forest the event handed back.

It returns an `Output/RenderResult`: the finished rendered forest, the layout reference, and an optional `Output/Index/ResolvedValueIndex`. Both format answers come from the route — the `RenderingMode`, and whether the format collects a value index.

Output encoding follows the returned render result. [Rich-text rendering](../Mapping/README.md#rich-text-rendering)
documents sanitization; [ordinary root delivery](../Layout/Element/Context/docs/consumers.md#ordinary-root-delivery)
and [mapping outcomes](../Mapping/README.md#stored-mappings-and-rendering) define the values delivery produces.

The duplicate-ID checks run before lowering and after finalization because a listener can replace the tree. Wiring validation judges the pre-prune forest; redistribution derives providers only on the surviving tree. These invariants and their rationale are in [rendering principles](principles/rendering.md).
