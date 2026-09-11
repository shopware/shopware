# Custom Event Listeners

The plugin-facing authoring guide for a rendering-lifecycle listener: the two events, where each one sits in the pipeline, and what each may change. The element members, a worked listener, cache tags and priority are in [listener-api.md](listener-api.md).

Listeners modify elements before or after rendering: computing derived values, transforming structure, resolving custom placeholders.

| Event                         | When                                               | Purpose                                  |
|-------------------------------|----------------------------------------------------|------------------------------------------|
| `ContentTreePreparationEvent` | Before every pipeline step, so before data loading | Modify layout tree, resolve placeholders |
| `RenderedTreeFinalizationEvent` | After data loading and every tree-shaping step | Enrich data, transform property values |

`ContentPipeline::load()` calls its own preparation and finishing steps directly rather than through these events, so the tree a listener sees does not depend on its priority. A `ContentTreePreparationEvent` listener sees the raw loaded layout, before every step in [Execution Order](../README.md#execution-order). A `RenderedTreeFinalizationEvent` listener sees the finished rendered tree, after the virtual-root unwrap and the partial extract, and before the pipeline's second duplicate-element-id check, which judges the tree the listener handed back.

That places the two events on opposite sides of language reduction, the preparer's first pass. A `translatable: true` property is stored as a language map, language id to string. A preparation listener reads and writes that whole map and must keep it a map of strings — a non-map value, or a map whose selected entry is not a string, reaches reduction as `CONTENT_SYSTEM__TRANSLATION_SHAPE_INVALID` (500). A finalization listener reads the selected string, or in SKELETON no property value at all, and returns a map-free tree either way; a map reintroduced there is its own defect, unscanned for.

The two carry the tree in the model of their own position, and each exposes one way to put a changed tree back:

- `ContentTreePreparationEvent::tree()` — `list<StoredElement>`; a replacement goes back through `replaceTree()`, because a stored element is immutable and an edit produces new instances
- `RenderedTreeFinalizationEvent::tree()` — `list<RenderedElement>`; a replacement goes back through `replaceTree()`, because a rendered element is immutable too

Both expose the same remaining properties, all readonly:

- `layout` — `LayoutReference` exposing `id`, `name`, `version` of the rendered layout
- `specification` — `RenderingSpecification`
- `salesChannelContext` — `SalesChannelContext`
- `cacheContext` — `RenderingCacheContext`, for cache tag management (readonly reference, but methods mutate state)

Neither event exposes `RenderingMode`, and both are dispatched at the same position in both modes. That is deliberate: a listener's structural output must not depend on the rendering mode. Property values are empty in SKELETON and populated in FULL, so deriving structure, slots or style from a property value produces a tree that differs between a cached skeleton and the later full response, which breaks their composition. Mode remains observable indirectly (the per-format route name on the specification's request, property emptiness, loader effects on the cache context); the bar is a contract, not something the event shape can enforce.

### What a finalization listener may change

The tree a listener hands back through `replaceTree()` is what the response carries, and the pipeline checks it for a repeated element id before building anything from it. Only one edit is constrained:

| Edit | Result |
|------|--------|
| Rewrite property values | Supported, the whole point of the event |
| Remove an element or a subtree | Supported |
| Reorder elements or slot children | Supported |
| Add an element with a new id | Supported |
| Duplicate an existing element id | Fails the render, `CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID` (500) |

Element ids are a rendered-model contract, not bookkeeping: partial extraction addresses by id, the storefront emits `data-element-id`, and the decomposed format's `assignments` are keyed by it. The pipeline rejects a repeated id twice — once over the pre-prune stored forest, once over the forest this event hands back — so a stored forest carrying one fails just as a listener's duplicate does. Structural validity is otherwise the listener's responsibility; nothing repairs a tree a listener hands back.

`RenderedTreeEditor::mapNodes()` is the whole-tree edit idiom: it visits every existing node exactly once and replaces it with exactly one node, so it neither mints nor drops nodes. That guarantee is about cardinality only. The mapper's signature is `callable(RenderedElement): RenderedElement` and whatever it returns lands in the tree, so nothing stops it returning an element whose id already exists elsewhere in the forest, or one carrying extra slot children. Using the idiom does not discharge the id obligation — that stays with the listener, as above.

Placeholder resolution runs in FULL mode only and before this event, so a listener introducing a `{{token}}` resolves it itself rather than expecting the pipeline to.
