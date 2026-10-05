# Layout

Content layout tree structure and processing. Layouts are reusable templates containing nested content elements with slots.

## Architecture

1. **Element Structure** (Element/) — the stored element model (`StoredElement`, `StoredValue`) plus `RenderedTreeEditor`, the whole-tree edit idiom for a rendered forest
2. **DAL Definitions** (Entity/, Field/) — Database schema and custom field serializers
3. **Scaffolding** (Scaffolding/): The stored-tree preparer, the virtual-root wrapper it drives and the records the preparer hands back, detailed under Subdirectories. The preparer's pass order is owned by [../docs/pipeline-steps.md](../docs/pipeline-steps.md)
4. **Default Seeding** (`LayoutDefaultSeeder`) seeds element-type primitive defaults into the stored tree at the DAL write boundary, invoked from the `Field/` layout serializer's `normalize` hook

## Default Seeding

`LayoutDefaultSeeder` walks an element forest and, per node, fills each primitive property of the node's `component` type whose default is non-null and whose key is absent, recursing into every slot's children. It no-ops on an unregistered `component`. It takes a `StoredElement` forest only (the field serializer's `normalize` decodes either payload shape into a `StoredTree` before the write boundary runs) and shares `Type/PrimitiveDefaultProvider` with the layout mutations. A stored element is immutable, so seeding one rebuilds its subtree and returns a new forest. It runs in the `StoredElementListFieldSerializer::normalize` write step. See [Every layout write passes through the write boundary, a single choke point](../docs/principles/write-admission.md#every-layout-write-passes-through-the-write-boundary-a-single-choke-point).

## Multi-Root Layouts

ContentLayoutEntity can contain multiple root elements. Each root is an independent tree for element-provided context: providers in one root CANNOT provide to elements in another root. Root-ambient context is the exception: the same ambient map is an input to the render of every root, so a `scope: "root"` consumer in any root receives it. Element ids are unique across all roots so partial rendering can address one ([stored-model.md](../docs/principles/stored-model.md#an-element-id-is-an-opaque-string-unique-across-all-roots-of-a-layout)).

## Subdirectories

- **[Element/](Element/README.md)** - Stored element model, the rendered-forest edit idiom, context and data requirement definitions
- **Entity/** - DAL definitions (`ContentLayoutDefinition`); repository `content_layout.repository`; ids generated with `Uuid::randomHex()`; the layout column serializes through the `Field/` serializers
- **[Field/](Field/README.md)** - Custom DAL field types and serializers (infrastructure)
- **Scaffolding/** - `StoredTreePreparer` (the one component that brings a stored forest into renderable shape, in the pass order [../docs/pipeline-steps.md](../docs/pipeline-steps.md) owns), `VirtualRootWrapper` (wraps the stored roots to carry the page-level placeholder values, recognises its own wrapper on the stored post-prune forest, and unwraps it again after the render step, as one of the finishing steps on the rendered forest), and the two records the preparer returns. `TreePreparationResult` carries the pruned tree, the pre-prune forest (which the pipeline's wiring validation judges) and the `RenderScaffolding`; `RenderScaffolding` is the immutable record the finishing steps read instead of re-deriving. Only `virtualRootSurvivedPrune` is read off the post-prune tree; `extractTargetId` is normalised from the `RenderingSpecification` before the prune, and the prune itself consumes it
- **[Type/](Type/README.md)** - Element type system: declarative type definitions, YAML loading, registry, app integration
