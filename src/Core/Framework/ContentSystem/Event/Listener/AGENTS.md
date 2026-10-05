## Constraints

- Never rely on the pipeline resolving a placeholder in SKELETON mode, or one that a `RenderedTreeFinalizationEvent` listener adds; resolve it in the listener. Check: does a `{{token}}` the listener introduces reach a FULL-mode preparation step before the finalization event? Why: [rendering.md](../../docs/principles/rendering.md#the-two-tree-replacement-events-fire-at-fixed-points)
- Return from either event a tree with no repeated element id, and from `ContentTreePreparationEvent` also valid wiring. Check: can the tree passed to `replaceTree()` repeat an element id or, after preparation, break wiring? Either fails the render under [the final check](../../docs/principles/rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode).
- Before adding a guard or rule on listener output, list every action the listener contract permits with its outcome (throw, drop or a new category). Never let a permitted action throw, and document a rule no guard can enforce. Check: can any permitted action reach the new throw? Why: [extension-surface.md](../../docs/principles/extension-surface.md#a-guard-on-listener-output-never-throws-on-an-action-that-the-extension-contract-permits)

## Where to look

- The tree each event carries, `replaceTree()`, the private tree storage, the readonly event properties, the lack of `RenderingMode`, and the edits a finalization listener may make: [custom-listeners.md](docs/custom-listeners.md#custom-event-listeners)
- Editing `RenderedElement`, `RenderedTreeEditor::mapNodes()` and null properties: [listener-api.md](docs/listener-api.md#working-with-renderedelement)
- A worked listener, registering it with `#[AsEventListener]` and the autoconfigure requirement: [listener-api.md](docs/listener-api.md#example-reading-time-listener)
- Cache tags and disabling the cache from a listener: [listener-api.md](docs/listener-api.md#cache-context-in-subscribers)
- Priority ordering against other extensions' listeners: [listener-api.md](docs/listener-api.md#priorities)
- Pipeline step order and what a listener sees at each position: [README.md](README.md#execution-order)
