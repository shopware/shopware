# Layout

Content layout tree structure and processing. Layouts are reusable templates containing nested content elements with slots.

## Architecture

Four responsibilities:

1. **Element Structure** (Element/): ContentElement tree with slots, visitor pattern for traversal
2. **Scaffolding** (Scaffolding/): Layout wrapping utilities (virtual root wrapper)
3. **DAL Definitions** (Entity/, Field/): Database schema and custom field types
4. **Loading** (Loader/): ContentLayoutEntity retrieval from repository

## Key Classes

- `ContentElement` - Tree aggregate root, slots for nesting (Element/)
- `LayoutLoader` - Loads ContentLayoutEntity from repository (Loader/)
- `VirtualRootWrapper` - Wraps/unwraps layout with virtual root (Scaffolding/)
- `ContentLayoutEntity` - Layout DAL entity (Entity/)

## Content Element Tree

ContentElement contains:
- `component`: Element component type identifier
- `properties`: Configuration values (may contain placeholders like `{{productId}}`)
- `slots`: Named slots containing child elements
- `dataRequirements`: What data to load (source + criteria)
- `contextDefinitions`: Providers/consumers for context distribution

Elements form tree via slots. Visitor pattern for traversal. Placeholders resolved after entity ID resolution.

## Multi-Root Layouts

ContentLayoutEntity can contain multiple root elements (`array<ContentElement>`). Each root is an independent tree with separate context scope.

**Critical Constraint:** Context providers in one root element CANNOT provide context to elements in another root. Context distribution is tree-scoped (within a single root's descendants), not layout-scoped.

**Element ID Uniqueness:** Element IDs should be unique across all root elements in the layout, not just within a single tree. Partial rendering (`?elementId=xyz`) searches across all roots and returns the first match.

## Custom DAL Fields

Field/ contains custom DAL field types for serializing complex structures:
- `ContentElementField`: Serializes a single ContentElement
- `ContentElementListField`: Serializes arrays of ContentElement (multi-root layouts)
- `ElementSlotsField`: Serializes slot arrays (`array<string, SlotContent>`)
- `DataRequirementsField`: Serializes data requirements
- `ContextProvidersField`, `ContextConsumersField`: Serializes context definitions

Each field has corresponding serializer. These aren't architectural modules - they're DAL infrastructure.

## Subdirectories

- Element/: ContentElement tree structure, visitor pattern
- Loader/: ContentLayoutEntity loading from repository
- Scaffolding/: Layout wrapping utilities (VirtualRootWrapper)
- Entity/: DAL definitions (ContentLayoutDefinition)
- Field/: Custom DAL field types and serializers
