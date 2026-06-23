@README.md

## Constraints

- Multi-root layouts: context is tree-scoped, not layout-scoped
- Element IDs unique across all roots (partial rendering searches all)
- Placeholders (`{{key}}`) resolved in single pass — no recursive resolution
- Field/ serializers are infrastructure — only interact in EntityDefinition classes
