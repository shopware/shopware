# Output

Post-hydration content transformation before response. Extracts sub-trees and prepares final output for API serialization.

## Key Classes

- `SubTreeExtractor` - Extracts specific element sub-tree for partial rendering
- `RenderingSpecification` - Configuration for rendering (layout ID, placeholders, target element ID)

## Pipeline Position

```
Entity ID → Load → PreHydration Events → Hydration → PostHydration Events → **Output** → Response
```

Output operates on fully hydrated ContentElement trees. Unlike event subscribers (layout preparation) or Hydration (data loading), Output works with populated trees and prepares them for response serialization.

## Response Format Transformation

ContentPage provides lazy transformations to alternative formats:
- `getContentDecomposedPage()` → **ContentDecomposedPage**: Skeletons + deduplicated data + assignments
- `getContentSkeletonPage()` → **ContentSkeletonPage**: Element trees without hydrated data
- `getContentDataPage()` → **ContentDataPage**: Data + assignments without skeleton

Routes use these transformations: ContentRoute returns ContentPage directly, others transform.

## Partial Rendering

Extract specific element and descendants from full tree, discard ancestors and siblings. Used when client needs portion of page (AJAX updates, lazy loading). Two-phase process: event subscribers prune pre-hydration to keep context path, then extract post-hydration.

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

### Multi-Root Search Behavior

When layout contains multiple root elements, SubTreeExtractor searches trees sequentially:
- Iterates root elements in order
- Searches each tree for target element ID
- Returns first match found, stops searching remaining roots
- Throws exception only if NO match found in ANY root

**Edge case:** If same elementId exists in multiple roots, only first occurrence returned.

## Characteristics

Output operates on hydrated trees with these characteristics:
- Works with fully hydrated content (all data loaded, context resolved)
- Read-only transformations (extraction, filtering)
- No database queries (data already in memory)
- Fast operations (selection only)

Output does NOT modify element properties or load additional data. All data concerns resolved before Output runs.

## Extension Potential

Module designed for extensibility via tagged services or event subscribers. Potential additions: metadata enrichment, permission-based filtering, client-specific transformations.

## Subdirectories

- Struct/: Data structures (ContentPage, ContentDecomposedPage, ContentSkeletonPage, ContentDataPage)
