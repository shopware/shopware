# Context

Context provider and consumer definitions for content elements.

## Key Classes

- `ContextProvider` - Defines what context an element exposes to descendants
- `ContextConsumer` - Defines what context an element receives from ancestors
- `ContextDefinitions` - Container holding providers and consumers for an element
- `ContextDependencyAnalyzer` - Analyzes context dependencies for tree pruning

## Redistribution

`ContextConsumer::$redistribute` auto-generates a broadcast provider when `true` — expanded at runtime by `RedistributeExpansionSubscriber`, not persisted.

## Aliases

- **Consumer Alias** (`$consumerAlias`): Transforms key when redistributing. Requires `redistribute: true`
- **Property Alias** (`$propertyAlias`): Renames storage key. No dots allowed, unique per element. Independent of redistribute

## Subdirectories

- **Distribution/** - Distribution strategy value objects (Broadcast, Indexed, Keyed, Sliced, Iterator)
