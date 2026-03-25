# Element

@README.md

## Source Code References

- `ContentElement` - Tree aggregate root (slots stored as `array<string, SlotContent>`)
- `SlotContent` (Slot/) - Collection of elements per slot
- `ContextProvider`, `ContextConsumer` (Context/) - Context definitions
- `ContextDependencyAnalyzer` (Context/) - Analyzes context dependency chains
- `DataRequirement` (DataRequirement/) - Data loading specs
- `ElementVisitor` (Visitor/) - Traversal interface

## Constraints

### Multi-Root Context Isolation

Layouts can have multiple root elements (`array<ContentElement>`). Each root is an independent context tree:
- Context providers in root #1 cannot provide to root #2
- Context distribution is tree-scoped, not layout-scoped
- Element IDs must be unique across all roots for partial rendering

### Property Accessors (Null-Safe)

All property getters return null for missing keys. Never throw exceptions.

Use `hasProperty()` or null coalescing:

```php
$value = $element->getProperty('nonExistent');  // null, NOT exception
$value = $element->getProperty('exists') ?? 'default';
```

### Generator Pattern: allSlotElements()

Memory-efficient tree traversal. DO NOT convert to array.

```php
// Right: Direct iteration
foreach ($element->allSlotElements() as $childElement) {
    // Process each element
}

// Wrong: Converting to array
$all = iterator_to_array($element->allSlotElements());  // DON'T DO THIS
```

## Context System

### Provider vs Consumer

**Provider** exposes data as context. See `ContextProvider` class.

**Consumer** receives data from context. See `ContextConsumer` class.

### Context Matching Rules

- **Matched by key**: Provider key 'product' → Consumer key 'product'
- **Flows down tree**: Ancestors provide to descendants, not siblings
- **Property keys independent**: Provider stores in one property, consumer receives in another
- **Multiple consumers**: Single provider can serve multiple consumers
- **Shadow semantics**: Inner provider shadows outer for same key

### Context Methods

```php
// Check if element consumes specific context
$element->acceptsContext('product', $pathResolver);

// Find direct children that consume context
$consumers = $element->collectConsumers('product', $pathResolver);

// Get all context providers/consumers
$providers = $element->getProvidesContext();
$consumers = $element->getAcceptsContext();
```

## Data Requirements

### DataRequirement Structure

See `DataRequirement` class for structure.

- `key`: Where result will be stored in element property
- `source`: Loader identifier (e.g., 'entity', 'product_listing')
- `config`: Loader-specific configuration

After hydration, data accessible via `$element->getProperty($key)`.

## Visitor Pattern

### Traversal Order (Depth-First)

See `ContentElement::traverse()` for implementation. Executes `enter()` before children, `leave()` after children.

**Mutation Safety Warning**: Don't mutate tree structure during traversal unless visitor specifically designed for it.

## Placeholder Resolution

### Placeholder Syntax

Properties may contain `{{placeholder}}` strings before placeholder resolution.

### replacePlaceholders() Method

See `ContentElement::replacePlaceholders()` for implementation.

Replaces placeholders with values from ResolvedData. Only scalar values replaced. Recursive - replaces in all descendant elements.

## Quick Reference

- **Constructor**: Requires `id` and `component`, rest optional with defaults
- **Property access**: Null-safe getters, use `hasProperty()` or null coalescing
- **Slots**: Array of SlotContent, multiple elements per slot (SlotContent is collection)
- **Generator**: `allSlotElements()` for memory-efficient traversal of direct slot elements
- **Slot access**: `getSlots()` returns `array<string, SlotContent>`, use `hasSlots()` or `slotsIsEmpty()` to check
- **Context**: Provider exposes, consumer receives, matched by key
- **Context redistribution**: `ContextConsumer::$redistribute` auto-generates provider (see ContextConsumer class)
- **Data requirements**: Key determines property storage after hydration
- **Visitor**: Depth-first, enter/leave hooks, don't mutate during traversal
- **Placeholders**: `{{key}}` syntax, resolved via event subscribers
