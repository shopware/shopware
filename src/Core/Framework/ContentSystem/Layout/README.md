# Layout

Content layout tree structure and processing. Layouts are reusable templates containing nested content elements with slots.

## Architecture

1. **Element Structure** (Element/) — the stored element model (`StoredElement`, `StoredValue`) plus `RenderedTreeEditor`, the whole-tree edit idiom for a rendered forest
2. **DAL Definitions** (Entity/, Field/) — Database schema and custom field serializers
3. **Scaffolding** (Scaffolding/) — The stored-tree preparer and the virtual-root wrapper it drives, plus the two records the preparer hands back: `TreePreparationResult` and, inside it, the `RenderScaffolding` carrying the wrap outcome to the finishing steps
4. **Default Seeding** (`LayoutDefaultSeeder`) seeds element-type primitive defaults into the stored tree at the DAL write boundary, invoked from the `Field/` layout serializer's `normalize` hook

## Default Seeding

`LayoutDefaultSeeder` walks an element forest and, per node, fills each primitive property of the node's `component` type whose default is non-null and whose key is absent, recursing into every slot's children. It never overwrites an existing value and no-ops on an unregistered `component`. It takes a `StoredElement` forest only — the field serializer's `normalize` decodes either payload shape into a `StoredTree` before the write boundary runs — and shares the per-type rule (`Type/PrimitiveDefaultProvider`) with the layout mutations. A stored element is immutable, so seeding one rebuilds its subtree and returns a new forest rather than filling the one it was handed. Running it at the `StoredElementListFieldSerializer::normalize` write seam means every tree reaching `content_layout` through the DAL carries its type defaults, including paths that bypass the `Mutation/` ops (direct DAL write, Sync API, import, fixtures). Raw-SQL and migration writes go through `Connection`, not the DAL, so they bypass this seam.

## Multi-Root Layouts

ContentLayoutEntity can contain multiple root elements. Each root is an independent tree with separate context scope — providers in one root CANNOT provide to elements in another root. Element IDs must be unique across all roots for partial rendering.

## Subdirectories

- **[Element/](Element/README.md)** - Stored element model, the rendered-forest edit idiom, context and data requirement definitions
- **Entity/** - DAL definitions (ContentLayoutDefinition)
- **[Field/](Field/README.md)** - Custom DAL field types and serializers (infrastructure)
- **Scaffolding/** - `StoredTreePreparer` (the one component that brings a stored forest into renderable shape: placeholder resolution, then the virtual-root wrap, then the partial prune), `VirtualRootWrapper` (wraps the stored roots for page-level context, recognises its own wrapper on the stored post-prune forest, and unwraps it again after the render step, as one of the finishing steps on the rendered forest), and the two records the preparer returns. `TreePreparationResult` carries the pruned tree, the pre-prune forest (which the pipeline's wiring validation judges, so a defect in a discarded subtree still fails the render) and the `RenderScaffolding`; `RenderScaffolding` is the immutable record the finishing steps read instead of re-deriving. Only `virtualRootSurvivedPrune` is read off the post-prune tree; `extractTargetId` is normalised from the `RenderingSpecification` before the prune, and the prune itself consumes it
- **[Type/](Type/README.md)** - Element type system: declarative type definitions, YAML loading, registry, app integration
