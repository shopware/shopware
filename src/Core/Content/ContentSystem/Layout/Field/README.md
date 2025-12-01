# Field

Custom DAL field types for persisting ContentElement structures to JSON. Each field has a corresponding serializer handling encode/decode.

## Key Classes

- `ContentElementField` / `ContentElementFieldSerializer` - Single ContentElement
- `ContentElementListField` / `ContentElementListFieldSerializer` - ContentElement arrays
- `ElementSlotsField` / `ElementSlotsFieldSerializer` - Slot arrays
- `DataRequirementsField` / `DataRequirementsFieldSerializer` - Data requirements
- `ContextProvidersField` / `ContextProvidersFieldSerializer` - Context providers
- `ContextConsumersField` / `ContextConsumersFieldSerializer` - Context consumers

## Field-Serializer Pattern

Each custom field type extends `JsonField` and specifies its serializer via `getSerializerClass()`. Serializers extend `AbstractFieldSerializer` and implement:

- `encode()` - Object/array → JSON for database storage
- `decode()` - JSON → domain objects (ContentElement, DataRequirement, etc.)

## Composition

Serializers compose each other for nested structures. `ContentElementFieldSerializer` delegates to child serializers for slots, data requirements, and context definitions. This enables recursive ContentElement tree serialization.

## Validation

Serializers implement `getConstraints()` to validate JSON structure during write operations. Uses Symfony Validator's `Collection` constraint with `allowMissingFields: false` combined with `Optional` on individual fields. This seals the array structure: all defined keys must be present (no extra, no missing), but `Optional`-wrapped values may be null.

