> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Multi-root layouts: context is tree-scoped, not layout-scoped
- Element IDs unique across all roots (partial rendering searches all)
- Placeholders (`{{key}}`) resolved in single pass — no recursive resolution
- Field/ serializers are infrastructure — only interact in EntityDefinition classes
