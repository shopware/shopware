# Field

The DAL field type behind the `content_layout.layout` column, and its serializer. One field, one serializer, registered with the `shopware.field_serializer` DAL tag so the framework's serializer registry discovers it.

## Key Classes

- `StoredElementListField` - the column's `ListField`. It declares no per-item field type: a stored element is a fixed wire shape owned by `Layout/Codec/StoredElementCodec`, not a DAL field, so the generated Admin-API schema describes an item as a closed object
- `StoredElementListFieldSerializer` - the stored element forest of that column. Both storage directions delegate to `Layout/Codec/StoredTreeCodec` and the write's constraint pass to `Layout/Codec/StoredTreeConstraints`, so the wire shape is defined once: `encode` writes what a later `decode` reads back, and nothing the constraint pass admits can fail decode. A defect the codec raises on the write payload becomes a `WriteConstraintViolationException` carrying that defect's own error code. Its `normalize` hook is the write's first decode, ahead of the resolvability gate: it decodes the payload into a `Layout/StoredTree`, rejects a duplicate element id via `StoredTree::validate()`, admits the tree through `Layout/LayoutWriteBoundary::apply()`, re-encodes it to arrays, and memoizes the admitted tree on the write `Context` (`Layout/LayoutWriteContext`) so the gate judges it without decoding the column again. That drops the gate's decode, not `encode`'s — a normal write decodes twice
