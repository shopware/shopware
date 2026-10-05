## Navigation

- Why listener actions are listed before any guard: [extension-surface.md](../../docs/principles/extension-surface.md)
- Why these constraints hold, and what was not chosen: [rendering.md](../../docs/principles/rendering.md)

## Constraints

- Placeholders resolved in single pass, on the stored tree, after `ContentTreePreparationEvent` and in FULL mode only — a listener MUST NOT rely on the pipeline resolving anything in SKELETON mode
- Both events carry their forest in private storage and replace it via `replaceTree()`: `ContentTreePreparationEvent` the stored one, `RenderedTreeFinalizationEvent` the rendered one. Every other event property is readonly, and neither exposes `RenderingMode`
- Expect a check of the returned tree again. A repeated id fails after either event, under [the render's final check](../../docs/principles/rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode), and invalid wiring fails after preparation. A finalization listener may still rewrite, remove, reorder and add elements. Check: `ContentPipelineTest`.
- Extension: `#[AsEventListener]` attribute with event class and priority
- Before adding a guard or rule on listener output, list every action the listener contract permits with its outcome (throw, drop or a new category). Never let a permitted action throw, and document a bar no sound guard can hold. Check: can any permitted action reach the new throw?
