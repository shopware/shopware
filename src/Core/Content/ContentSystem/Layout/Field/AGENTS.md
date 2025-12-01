# Field

@README.md

## Source Code References

- `ContentElementFieldSerializer` - Root serializer, composes all others
- `ContentElementListFieldSerializer` - Wraps ContentElementFieldSerializer for arrays
- `ElementSlotsFieldSerializer` - Recursive slot serialization
- `DataRequirementsFieldSerializer` - Data requirement maps
- `ContextProvidersFieldSerializer` - Provider definitions with distribution configs
- `ContextConsumersFieldSerializer` - Consumer definitions with validation

## Serializer Composition

`ContentElementFieldSerializer` is the composition root. Constructor injects:
- `DataRequirementsFieldSerializer`
- `ContextProvidersFieldSerializer`
- `ContextConsumersFieldSerializer`
- `ElementSlotsFieldSerializer`

`ElementSlotsFieldSerializer` injects `ContentElementFieldSerializer` for recursive tree serialization.

## Quick Reference

- **Base class**: All fields extend `JsonField`
- **Serializer base**: All serializers extend `AbstractFieldSerializer`
- **Entry point**: `ContentElementFieldSerializer::decodeElement()` for programmatic deserialization
- **Validation**: Serializers validate JSON structure during encode via Symfony Validator
- **Usage**: Only in `ContentLayoutDefinition` - infrastructure, not domain API
