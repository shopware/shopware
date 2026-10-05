## Constraints

- Keep `root_source` `Required` and `Immutable` on `ContentLayoutDefinition`: a layout holds one root source, and rejects an assignment to another kind. Check: can the field change after creation, or one layout serve two root sources? [stored-model.md](../../docs/principles/stored-model.md)

## Where to look

- The layout repository, id generation and how the layout column serializes: [README.md](../README.md#subdirectories)
- The layout column's field and serializer: [Field/README.md](../Field/README.md#key-classes)
