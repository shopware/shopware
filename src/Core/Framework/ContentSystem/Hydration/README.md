# Hydration

Loads data and resolves context for content elements in two mandatory phases.

## Key Class

- `ContentElementHydrator` - Entry point, orchestrates loading + context resolution

## Two-Phase Process

1. **Data Loading**: Depth-first traversal, fetches data per element's `DataRequirement`. Each loader returns `ContentDataLoaderResult` with cache info. Data stored in element property by requirement key. See DataLoader/.
2. **Context Resolution**: Separate pass after ALL data loaded. Distributes context from providers to direct children. See DataContext/.

Data loading MUST complete before context resolution because providers may expose loaded data as context. `hydrate()` returns a `Generator<ContentElement>`.

## Subdirectories

- **DataLoader/** - Data fetching (`AbstractContentDataLoader` implementations)
- **DataContext/** - Context distribution (`DataContextResolver`)
