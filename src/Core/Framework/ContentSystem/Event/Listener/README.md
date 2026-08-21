# Listener

Event-driven extension points for the content rendering lifecycle. A listener replaces the stored forest via `ContentTreePreparationEvent::replaceTree()` before data loading, and replaces the rendered forest via `RenderedTreeFinalizationEvent::replaceTree()` after it. Neither event exposes its forest for mutation: both hold it privately behind `tree()`, and every element is immutable, so an edit produces new instances that only `replaceTree()` can put back.

## Guides

- [docs/custom-listeners.md](docs/custom-listeners.md) - The plugin-facing guide to writing a rendering-lifecycle listener.

## Execution Order

Core ships no listener on either event. `ContentPipeline::load()` (module root) calls its preparation and finishing steps directly, in this order:

After `ContentTreePreparationEvent` is dispatched:
1. Placeholder resolution — resolves `{{variable}}` placeholders from the specification on the stored tree, in FULL mode only (`Layout/Scaffolding/StoredTreePreparer`, whose remaining steps follow)
2. Virtual-root wrap — wraps the stored roots with a temporary container for layout-level context (`Layout/Scaffolding/VirtualRootWrapper`), after the placeholder resolution
3. Partial prune — prunes the stored tree when `targetElementId` is specified (`Output/PartialRenderer`), after the virtual-root wrap; it ends the preparer's work, which hands back both the pruned tree and the forest as it stood before this step
4. Duplicate-element-id check — rejects a repeated element id (`CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID`, 500), judging the pre-prune forest, so a twin the prune or the later target extract would have removed still fails the request instead of leaving the response serving one of two ambiguous elements
5. Wiring validation — rejects a context-wiring defect, judging the same pre-prune forest, so a defect inside a subtree the prune discarded still fails the request
6. Redistribute derivation — expands `redistribute: true` into broadcast providers on the surviving stored tree, after the validation and before the render step; it throws nothing
7. Render step — turns the derived stored tree into the rendered forest (`Rendering/ElementLowering`: forest-wide data resolution, context-delivery resolution, then the mint; FULL does all three, SKELETON mints structure only)

**After the render step**, before `RenderedTreeFinalizationEvent` is dispatched:
1. Virtual-root unwrap — removes the virtual root wrapper, on the rendered forest
2. Partial extract — extracts the target subtree for the response, on the rendered forest

**After `RenderedTreeFinalizationEvent`**, on the tree the event handed back:
1. Duplicate-element-id check — rejects a repeated element id in that forest (`CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID`, 500). Not a scaffolding-driven finishing step: it reads no `RenderScaffolding` and runs on every request, in either rendering mode. It repeats the pre-lowering check because a listener may replace the tree, and a duplicate the replacement introduces is invisible to a check that ran before the render step. So a listener must not repeat an id; adding an element with a new id is allowed

So a `ContentTreePreparationEvent` listener always sees the raw loaded tree, and a `RenderedTreeFinalizationEvent` listener always sees the finished one — at any priority.

## Priorities

There are no reserved bands. Priority only orders extension listeners against each other on the same event.

Until this became true, the documented bands were not merely unenforced but *inverted*: every core listener was registered at priority 0 (the `#[AsEventListener(priority: …)]` attributes never took effect, because these services were not autoconfigured), so a plugin listener at any priority above 0 ran BEFORE all of core and only a negative priority ran after. A plugin written to the old `>= 6000` / `< 1000` guidance got the opposite of what it intended. Both bands are gone; re-check any listener whose priority was chosen to sit before or after a core step.
