## Navigation

- Why these constraints hold, and what was not chosen: [stored-model.md](../../docs/principles/stored-model.md)

## Quick Reference

- Repository: `content_layout.repository`
- ID generation: `Uuid::randomHex()`
- Serialization: Automatic via custom field serializers in `Field/`

## Constraints

- Keep `root_source` `Required` and `Immutable` on `ContentLayoutDefinition`: a layout holds one root source, and rejects an assignment to another kind. Check: can the field change after creation, or one layout serve two root sources?
