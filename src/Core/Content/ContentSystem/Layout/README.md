# Layout

Content layout tree structure and processing. Layouts are reusable templates containing nested content elements with slots.

## Architecture

Four responsibilities:

1. **Element Structure** (Element/): ContentElement tree with slots, visitor pattern for traversal
2. **Refinement** (Refinery/): Transform layout with resolved data (single-pass only)
3. **DAL Definitions** (Entity/, Field/): Database schema and custom field types

## Key Classes

- `ContentElement` - Tree aggregate root, slots for nesting (Element/)
- `LayoutRefinery` - Single-pass refinement orchestrator (Refinery/)
- `ContentLayoutEntity` - Layout DAL entity (Entity/)

## Content Element Tree

ContentElement contains:
- `type`: Element type identifier
- `properties`: Configuration values (may contain placeholders like `{{productId}}`)
- `slots`: Named slots containing child elements
- `dataRequirements`: What data to load (source + criteria)
- `contextDefinitions`: Providers/consumers for context distribution

Elements form tree via slots. Visitor pattern for traversal. Placeholders resolved after entity ID resolution.

## Custom DAL Fields

Field/ contains custom DAL field types for serializing complex structures:
- `ContentElementField`: Serializes element trees
- `ElementSlotsField`: Serializes slot collections
- `DataRequirementsField`: Serializes data requirements
- `ContextProvidersField`, `ContextConsumersField`: Serializes context definitions

Each field has corresponding serializer. These aren't architectural modules - they're DAL infrastructure.

## Subdirectories

- Element/: ContentElement tree structure, visitor pattern
- Refinery/: Layout refinement (single-pass constraint!)
- Entity/: DAL definitions (ContentLayoutDefinition, ContentLayoutAssignmentDefinition)
- Field/: Custom DAL field types and serializers
