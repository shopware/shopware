> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Placeholders resolved in single pass — listeners adding new placeholders MUST resolve them in the same dispatch cycle
- Only `$event->elements` is mutable — all other event properties are readonly
- Extension: `#[AsEventListener]` attribute with event class and priority
