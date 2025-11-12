# Layout

@README.md

## Source Code References

- `ContentElement` (Element/) - Tree aggregate root
- `LayoutRefinery` (Refinery/) - Single-pass refinement orchestrator
- `RefinedLayoutBuilder` (Refinery/) - Builds RefinedLayout from ContentLayoutEntity
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

### Single-Pass Refinery Constraint

**NO recursive placeholder resolution. Extension refiners adding placeholders MUST resolve them in the same pass.**

System won't re-run placeholder resolution. See Refinery/AGENTS.md for details.

## Custom DAL Fields (Field/ Directory)

Field/ contains custom DAL field types for persisting complex structures:

- `ContentElementField` - Serializes element trees to JSON
- `ElementSlotsField` - Serializes slot arrays (`array<string, SlotContent>`)
- `DataRequirementsField` - Serializes data requirement maps
- `ContextProvidersField`/`ContextConsumersField` - Serializes context definitions

These are **technical infrastructure, not domain concepts**. Only interact with them in EntityDefinition classes.

## Quick Reference

- **Tree structure**: Aggregate root with slots, visitor pattern for traversal
- **Generator**: `allSlotElements()` for memory-efficient traversal of direct slot elements
- **Property access**: Use `hasProperty()` or null coalescing
- **Placeholders**: `{{key}}` syntax, replaced during refinement
- **Custom fields**: DAL infrastructure in Field/, not architectural modules
- **Refinement**: Single-pass constraint (see Refinery/AGENTS.md)
