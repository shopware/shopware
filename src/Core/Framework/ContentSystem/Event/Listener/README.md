# Listener

Event-driven extension points for the content rendering lifecycle. A listener replaces the stored forest via `ContentTreePreparationEvent::replaceTree()` before data loading, and replaces the rendered forest via `RenderedTreeFinalizationEvent::replaceTree()` after it. Neither event exposes its forest for mutation: both hold it privately behind `tree()`, and every element is immutable, so an edit produces new instances that only `replaceTree()` can put back.

## Guides

- [docs/custom-listeners.md](docs/custom-listeners.md) - The plugin-facing guide to writing a rendering-lifecycle listener.
- [docs/listener-api.md](docs/listener-api.md) - The element members a listener edits, a worked listener, cache tags, and priority.

## Execution Order

Core ships no listener on either event. `ContentPipeline::load()` (module root) calls its preparation and finishing steps directly, in this order — the rules that make the order load-bearing are owned by [../../docs/pipeline-steps.md](../../docs/pipeline-steps.md):

After `ContentTreePreparationEvent` is dispatched:
1. Language reduction — replaces each translatable property's language map with the value the request's language chain selects (`Layout/Scaffolding/StoredTreePreparer`, whose remaining steps follow)
2. Placeholder resolution — resolves `{{variable}}` placeholders from the specification on the stored tree
3. Virtual-root wrap: wraps the stored roots with a temporary container carrying the page-level placeholder values (`Layout/Scaffolding/VirtualRootWrapper`), after the placeholder resolution
4. Partial prune — prunes the stored tree when `targetElementId` is specified (`Output/PartialRenderer`), after the virtual-root wrap; it ends the preparer's work, which hands back both the pruned tree and the forest as it stood before this step
5. Duplicate-element-id check — rejects a repeated id (`CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID`, 500), judging the pre-prune forest
6. Wiring validation — rejects a context-wiring defect, judging the same pre-prune forest, so a defect inside a subtree the prune discarded still fails the request. It reads no property value, so in SKELETON mode it receives the unreduced maps and is indifferent to them
7. Redistribute derivation — expands `redistribute: true` into broadcast providers on the surviving stored tree, after the validation and before the render step; it throws nothing
8. Render step: turns the derived stored tree into the rendered forest (`Rendering/ElementLowering`: forest-wide data resolution, the once-per-render root-ambient resolution of the specification's page-level data requirements when the specification declares any and the preparation wrapped, context-delivery resolution, then the mint; FULL does all four, SKELETON mints structure only)

**After the render step**, before `RenderedTreeFinalizationEvent` is dispatched:
1. Virtual-root unwrap — removes the virtual root wrapper, on the rendered forest
2. Partial extract — extracts the target subtree for the response, on the rendered forest

**After `RenderedTreeFinalizationEvent`**, on the tree the event handed back:
1. Duplicate-element-id check — rejects a repeated id in the forest the event handed back, again (`CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID`, 500); see [AGENTS.md](AGENTS.md) Constraints for why a listener must not repeat one

So a `ContentTreePreparationEvent` listener always sees the raw loaded tree, and a `RenderedTreeFinalizationEvent` listener always sees the finished one — at any priority. That places the two on opposite sides of language reduction: a preparation listener reads and writes whole language maps in either mode, while a finalization listener reads the reduced plain strings in FULL and no property values at all in SKELETON, and returns a map-free tree either way.

## Priorities

There are no reserved bands. Priority only orders extension listeners against each other on the same event. See [docs/listener-api.md](docs/listener-api.md) for what a plugin written against the old bands has to re-check.
