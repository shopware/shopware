# Field

Custom DAL field types for persisting element structures to JSON. Each field extends `JsonField` with a corresponding serializer for encode/decode. Each serializer is registered with the `shopware.field_serializer` DAL tag (seven in total) so the framework's serializer registry discovers it.

## Key Classes

- `ContentElementField` / `ContentElementFieldSerializer` - Single ContentElement
- `StoredElementListField` / `StoredElementListFieldSerializer` - the stored element forest of the `content_layout.layout` column. Both storage directions delegate to `Layout/Codec/StoredTreeCodec`, so the wire shape is defined once and `encode` writes what a later `decode` reads back; a defect the codec raises on the write payload becomes a `WriteConstraintViolationException` carrying that defect's own error code. Its `normalize` hook is the write's first decode, ahead of the resolvability gate: it decodes the payload into a `Layout/StoredTree`, rejects a duplicate element id via `StoredTree::validate()`, admits the tree through `Layout/LayoutWriteBoundary::apply()`, re-encodes it to arrays, and memoizes the admitted tree on the write `Context` (`Layout/LayoutWriteContext`) so the gate judges it without decoding the column again. That drops the gate's decode, not `encode`'s — a normal write decodes twice
- `ElementSlotsField` / `ElementSlotsFieldSerializer` - Slot arrays
- `ElementStyleField` / `ElementStyleFieldSerializer` - The element's universal `ElementStyle`. The write path validates it against the style option registry; the read path is registry-free and keeps unknown options verbatim (the strict write rejects them). Composed into `ContentElementFieldSerializer`
- `DataRequirementsField`, `ContextProvidersField`, `ContextConsumersField` - With matching serializers

Serializers compose each other for nested structures — `ContentElementFieldSerializer` delegates to child serializers for slots, requirements, and context definitions, enabling recursive tree serialization.
