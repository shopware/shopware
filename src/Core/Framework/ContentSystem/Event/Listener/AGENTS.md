@README.md

## Constraints

- Placeholders resolved in single pass — listeners adding new placeholders MUST resolve them in the same dispatch cycle
- Only `$event->elements` is mutable — all other event properties are readonly
- Extension: `#[AsEventListener]` attribute with event class and priority
