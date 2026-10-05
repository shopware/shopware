# Custom Event Listeners

Listeners replace the tree around data loading in `ContentPipeline::load()`: computing derived values, resolving custom placeholders.

| Event                         | When                                               | Purpose                                  |
|-------------------------------|----------------------------------------------------|------------------------------------------|
| `ContentTreePreparationEvent` | Before every pipeline step, so before data loading | Modify layout tree, resolve placeholders |
| `RenderedTreeFinalizationEvent` | After data loading and the [finishing steps](../../../docs/principles/README.md#glossary) | Enrich data, transform property values |

`ContentPipeline::load()` calls its own preparation and finishing steps directly rather than through these events, so the tree a listener sees does not depend on its priority. A `ContentTreePreparationEvent` listener sees the raw loaded layout, before every step in [Execution Order](../README.md#execution-order). A `RenderedTreeFinalizationEvent` listener sees the finished rendered tree, after `VirtualRootWrapper::unwrap()` and `PartialRenderer::extractTarget()`, and before the pipeline's duplicate-element-id check on the returned tree. It judges the tree the listener handed back.

The two carry the tree in the model of their own position, hold it privately behind `tree()`, and each exposes one way to put a changed tree back:

- `ContentTreePreparationEvent::tree()`: `list<StoredElement>`; a replacement goes back through `replaceTree()`, because a stored element is immutable and an edit produces new instances
- `RenderedTreeFinalizationEvent::tree()`: `list<RenderedElement>`; a replacement goes back through `replaceTree()`, because a rendered element is immutable too

Both expose the same remaining properties, all readonly:

- `layout` — `LayoutReference` exposing `id`, `name`, `version` of the rendered layout
- `specification` — `RenderingSpecification`
- `salesChannelContext` — `SalesChannelContext`
- `cacheContext` — `RenderingCacheContext`, for cache tag management (readonly reference, but methods mutate state)

Neither event exposes `RenderingMode`, and both are dispatched at the same position in both modes ([rule](../../../docs/principles/rendering.md#structure-is-a-function-independent-of-rendering-mode)). Mode remains observable indirectly: the per-format route name on the specification's request, property emptiness, loader effects on the cache context.

### What a finalization listener may change

The tree a listener hands back through `replaceTree()` is what the response carries, and the pipeline checks it for a repeated element id before building anything from it. Only one edit is constrained:

| Edit | Result |
|------|--------|
| Rewrite property values | Supported, the whole point of the event |
| Remove an element or a subtree | Supported |
| Reorder elements or slot children | Supported |
| Add an element with a new id | Supported |
| Duplicate an existing element id | Fails the render, under [the render's final check](../../../docs/principles/rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode) |

Structural validity is otherwise the listener's responsibility; nothing repairs a tree a listener hands back.

`RenderedTreeEditor::mapNodes()` is the whole-tree edit idiom: it visits every existing node exactly once and replaces it with exactly one node, so it neither adds nor drops nodes. That guarantee is about cardinality only. Whatever the mapper returns goes into the tree, so nothing stops it returning an element whose id already exists elsewhere in the forest, or one carrying extra slot children. Using the idiom does not satisfy the id obligation. That stays with the listener, as above.

Placeholder resolution runs in FULL mode only and before this event, so a listener introducing a `{{token}}` resolves it itself rather than expecting the pipeline to.
