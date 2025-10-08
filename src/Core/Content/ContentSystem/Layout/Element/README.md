# Element

ContentElement tree structure. Elements nest via slots, traverse via visitor pattern, declare data requirements and context.

## Key Class

- `ContentElement` - Tree aggregate root

## Structure

ContentElement contains:
- `id`: Unique identifier
- `type`: Element type
- `properties`: Configuration map (may contain `{{placeholder}}` strings)
- `slots`: ElementSlots containing named slots
- `dataRequirements`: What data to load (indexed by key)
- `contextDefinitions`: Providers and consumers for context distribution

### Slot Structure

`ElementSlots` contains `array<string, SlotContent>` - each slot has a name and can hold multiple elements (not just one). Common misconception: one element per slot. Reality: SlotContent is a collection.

```php
// Slot "header" can contain multiple elements
slots->get("header") → SlotContent([$element1, $element2])

// Iterate all elements from all slots
slots->allElements() → Generator yielding all elements
```

`allElements()` is a generator for memory efficiency. Doesn't build intermediate array. Visitor pattern uses this for tree traversal.

## Visitor Pattern

`traverse(ElementVisitor)` walks tree depth-first:
```
visitor.enter(element)
  for each child in slots:
    child.traverse(visitor)
visitor.leave(element)
```

Used for placeholder collection, context resolution, transformations. Don't mutate tree during traversal unless visitor is designed for it.

## Data Requirements

Data requirements declare what external data needs to be loaded during hydration. Each `DataRequirement` specifies:
- `key`: Property key where loaded data will be stored
- `source`: Data loader identifier (e.g., "entity", "product_listing")
- `config`: Loader-specific configuration (criteria, filters, associations)

All data requirements invoke their respective data loaders during hydration. The hydrator fetches the requested data and stores results in element properties by key.

## Context System

Elements provide/consume context via string keys. Provider exposes data at property key, consumer receives at same key.

### Context Matching

- Providers/consumers matched by context key (e.g., "product", "category")
- Provider has ContextProvider definition for key, exposes `element->getProperty(key)`
- Consumer has ContextConsumer definition for key, receives value in `element->setProperty(key, value)`
- Context flows down tree only (ancestors → descendants)

### Context Methods

- `acceptsContext(key)`: Returns true if element consumes this key
- `collectConsumers(key)`: Finds direct children consuming this key (not deep descendants)
- `getProvidesContext()`: Returns array of ContextProvider definitions
- `getAcceptsContext()`: Returns array of ContextConsumer definitions

Context distribution handled by DataContextResolver during hydration. See Hydration/DataContext/ for algorithm details.

## Placeholder Resolution

`replacePlaceholders(ResolvedData)` walks tree, replaces `{{key}}` strings in properties with values from ResolvedData. Only works with scalar values. Called after entity ID resolution, before hydration.

## Subdirectories

- Context/: ContextProvider, ContextConsumer, ContextDefinitions
- DataRequirement/: DataRequirement structure
- Slot/: ElementSlots container
- Visitor/: ElementVisitor interface, PlaceholderCollectorVisitor
