# Field

@README.md

## Purpose

DAL Serialization Infrastructure. **These are technical infrastructure, not architectural modules.**

## Source Code References

- `ContentElementField` + `ContentElementFieldSerializer` - ContentElement tree serialization
- `ElementSlotsField` + `ElementSlotsFieldSerializer` - Slot collection serialization
- `DataRequirementsField` + Serializer - Data requirement map serialization
- `ContextProvidersField` + Serializer - Context provider serialization
- `ContextConsumersField` + Serializer - Context consumer serialization

## Constraints

### Field + Serializer Pairs

Each field has matching serializer. See respective classes for implementation.

### Five Custom Field Types

1. **ContentElementField**: Serializes ContentElement trees (JSON storage, recursive)
2. **ElementSlotsField**: Serializes slot collections (JSON storage)
3. **DataRequirementsField**: Serializes data requirement maps (JSON storage, indexed by key)
4. **ContextProvidersField**: Serializes context providers (JSON storage)
5. **ContextConsumersField**: Serializes context consumers (JSON storage)

All serialize to JSON in database.

## Quick Reference

- **Purpose**: DAL serialization infrastructure, not domain modules
- **Five types**: ContentElement, ElementSlots, DataRequirements, ContextProviders, ContextConsumers
- **Storage**: All serialize to JSON
- **Usage**: Only in EntityDefinition field definitions
- **Serialization**: Automatic via repository, recursive for nested structures
