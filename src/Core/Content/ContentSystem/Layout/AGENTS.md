# Layout

@README.md

## Source Code References

- `ContentElement` (Element/) - Tree aggregate root
- `VirtualRootWrapper` (Scaffolding/) - Virtual root wrap/unwrap utilities
- `ContentLayoutEntity` (Entity/) - Layout DAL entity
- Custom field types (Field/) - DAL serialization infrastructure

## Constraints

### ContentElement Tree Structure

ContentElement contains:
- `component`: Element component type identifier
- `properties`: Configuration values (may contain placeholders like `{{productId}}`)
- `slots`: Named slots containing child elements (`array<string, SlotContent>`)
- `dataRequirements`: What data to load (source + criteria)
- `contextDefinitions`: Providers/consumers for context distribution

Elements form tree via slots. Use visitor pattern for traversal.

### Single-Pass Placeholder Resolution

**NO recursive placeholder resolution.** Placeholders are resolved once during `PlaceholderResolutionSubscriber` execution. See `EventSubscriber/AGENTS.md` for details.

## Custom DAL Fields (Field/ Directory)

Field/ contains custom DAL field types for persisting complex structures:

- `ContentElementField` - Serializes a single ContentElement to JSON
- `ContentElementListField` - Serializes arrays of ContentElement to JSON
- `ElementSlotsField` - Serializes slot arrays (`array<string, SlotContent>`)
- `DataRequirementsField` - Serializes data requirement maps
- `ContextProvidersField`/`ContextConsumersField` - Serializes context definitions

These are **technical infrastructure, not domain concepts**. Only interact with them in EntityDefinition classes.

## Quick Reference

- **Tree structure**: Aggregate root with slots, visitor pattern for traversal
- **Generator**: `allSlotElements()` for memory-efficient traversal of direct slot elements
- **Property access**: Use `hasProperty()` or null coalescing
- **Placeholders**: `{{key}}` syntax, resolved via event subscribers
- **Custom fields**: DAL infrastructure in Field/, not architectural modules
