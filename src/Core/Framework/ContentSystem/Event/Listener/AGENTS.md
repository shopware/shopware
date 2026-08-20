> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Placeholders resolved in single pass, on the stored tree, after `ContentTreePreparationEvent` and in FULL mode only — a listener adding new placeholders MUST resolve them in the same dispatch cycle, and MUST NOT rely on the pipeline resolving anything in SKELETON mode
- Both events carry their forest in private storage and replace it via `replaceTree()`: `ContentTreePreparationEvent` the stored one, `RenderedTreeFinalizationEvent` the rendered one. Every other event property is readonly, and neither exposes `RenderingMode`
- A finalization listener may rewrite property values, remove and reorder; it may NOT add an element or repeat an id, because the bridge pairs by element id and rejects both (500). The restriction ends with the bridge
- Extension: `#[AsEventListener]` attribute with event class and priority
