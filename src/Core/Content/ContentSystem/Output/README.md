# Output

Post-hydration content transformation before response. Extracts sub-trees and prepares final output for API serialization.

## Key Classes

- `SubTreeExtractor` - Extracts specific element sub-tree for partial rendering
- `RenderingSpecification` - Configuration for rendering (layout ID, placeholders, target element ID)

## Pipeline Position

```
Routing → Resolution → Layout → Refinement → Hydration → **Output** → Response
```

Output operates on fully hydrated ContentElement trees. Unlike Layout/Refinery (pre-hydration) or Hydration (data loading), Output works with populated trees and prepares them for response serialization.

## Partial Rendering

Extract specific element and descendants from full tree, discard ancestors and siblings. Used when client needs portion of page (AJAX updates, lazy loading).

Request via `?elementId=hero` parameter:

```
Full tree:        Partial (?elementId=hero):
Root              (discarded)
├─ Header         (discarded)
├─ Hero      →    Hero ← Extracted!
│  ├─ Title       ├─ Title
│  └─ Image       └─ Image
├─ Content        (discarded)
└─ Footer         (discarded)
```

SubTreeExtractor finds target element by ID, clones it with descendants. Ancestors and siblings discarded for reduced payload size.

## Characteristics

Output operates on hydrated trees with these characteristics:
- Works with fully hydrated content (all data loaded, context resolved)
- Read-only transformations (extraction, filtering)
- No database queries (data already in memory)
- Fast operations (selection only)

Output does NOT modify element properties or load additional data. All data concerns resolved before Output runs.

## Extension Potential

Module designed for extensibility via tagged services (similar to Layout/Refinery pattern). Potential additions: metadata enrichment, permission-based filtering, client-specific transformations.

Currently only partial rendering implemented via SubTreeExtractor.

## Subdirectories

- Struct/: Data structures (ContentPage, DecomposedContentPage)
