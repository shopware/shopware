> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Placeholders resolved in single pass, on the stored tree, after `ContentTreePreparationEvent` and in FULL mode only — a listener adding new placeholders MUST resolve them in the same dispatch cycle, and MUST NOT rely on the pipeline resolving anything in SKELETON mode
- A preparation listener is positioned ahead of every preparation pass ([../../docs/pipeline-steps.md](../../docs/pipeline-steps.md)), so it reads and writes a translatable property as its whole language map, keyed by language id; it MUST NOT write a bare string there, and a value the listener leaves non-map reaches reduction as `TRANSLATION_SHAPE_INVALID` (500)
- A finalization listener MUST return a map-free tree in either mode. In FULL it receives the reduced forest, where a translatable property is already a plain string; in SKELETON reduction never ran, but the mint strips every property value, so there is no map to receive either. A language map a listener introduces is the extension's own fault — no runtime scan guards it
- Both events carry their forest in private storage and replace it via `replaceTree()`: `ContentTreePreparationEvent` the stored one, `RenderedTreeFinalizationEvent` the rendered one. Every other event property is readonly, and neither exposes `RenderingMode`
- A finalization listener may rewrite property values, remove, reorder AND add an element; it may NOT repeat an element id. `ContentPipeline::load()` rejects a repeated id in the forest the event handed back, after the dispatch and before the result is assembled (`DUPLICATE_ELEMENT_ID`, 500, not a client defect)
- Extension: `#[AsEventListener]` attribute with event class and priority
