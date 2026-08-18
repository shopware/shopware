> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Placeholders resolved in single pass, on the stored tree, after `ContentTreePreparationEvent` and in FULL mode only — a listener adding new placeholders MUST resolve them in the same dispatch cycle, and MUST NOT rely on the pipeline resolving anything in SKELETON mode
- `ContentTreePreparationEvent` carries the stored forest and replaces it via `replaceTree()`; on `PostHydrationEvent` only `$event->elements` is mutable — all other event properties are readonly
- Extension: `#[AsEventListener]` attribute with event class and priority
