# Field

Custom DAL field types for persisting ContentElement structures to JSON. Each field extends `JsonField` with a corresponding serializer for encode/decode. Each serializer is registered with the `shopware.field_serializer` DAL tag (seven in total) so the framework's serializer registry discovers it.

## Key Classes

- `ContentElementField` / `ContentElementFieldSerializer` - Single ContentElement
- `ContentElementListField` / `ContentElementListFieldSerializer` - ContentElement arrays (the `content_layout.layout` column). Its `normalize` hook seeds the element types' primitive defaults into the write payload via `Layout/LayoutDefaultSeeder`, ahead of the resolvability gate
- `ElementSlotsField` / `ElementSlotsFieldSerializer` - Slot arrays
- `ElementStyleField` / `ElementStyleFieldSerializer` - The element's universal `ElementStyle`, validated and (de)serialized against the style option registry (strict write, drops orphaned options on read); composed into `ContentElementFieldSerializer`
- `DataRequirementsField`, `ContextProvidersField`, `ContextConsumersField` - With matching serializers

Serializers compose each other for nested structures — `ContentElementFieldSerializer` delegates to child serializers for slots, requirements, and context definitions, enabling recursive tree serialization.
