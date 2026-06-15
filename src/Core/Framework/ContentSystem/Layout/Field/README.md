# Field

Custom DAL field types for persisting ContentElement structures to JSON. Each field extends `JsonField` with a corresponding serializer for encode/decode.

## Key Classes

- `ContentElementField` / `ContentElementFieldSerializer` - Single ContentElement
- `ContentElementListField` / `ContentElementListFieldSerializer` - ContentElement arrays
- `ElementSlotsField` / `ElementSlotsFieldSerializer` - Slot arrays
- `DataRequirementsField`, `ContextProvidersField`, `ContextConsumersField` - With matching serializers

Serializers compose each other for nested structures — `ContentElementFieldSerializer` delegates to child serializers for slots, requirements, and context definitions, enabling recursive tree serialization.
