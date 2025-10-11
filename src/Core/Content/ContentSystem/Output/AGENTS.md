# Output

@README.md

## Source Code References

- `SubTreeExtractor` - Extracts element sub-tree for partial rendering
- `RenderingContext` - Configuration DTO for output processing
- `ContentPage` (Struct/) - Full content response structure
- `DecomposedContentPage` (Struct/) - Partial rendering response

## Constraints

### Post-Hydration Only

Runs AFTER hydration completes:
- All data loaded
- Context resolved
- Tree fully populated

See `ContentRouteLoader::load()` for pipeline order.

### Read-Only Operations

Output MUST NOT:
- Load additional data
- Query database
- Modify element properties

### No Data Dependencies

Output doesn't handle:
- Context consumer/provider chains
- Data requirements
- Placeholder resolution

## Partial Rendering

Request specific element via `?elementId=xyz` query parameter.

See `SubTreeExtractor::extract()` for implementation.

**Behavior:**
- Finds target element by ID
- Clones element + descendants
- Discards ancestors and siblings
- Returns reduced tree (or null if ID not found)

## Quick Reference

- **Pipeline**: After hydration, before response serialization
- **Input**: Fully hydrated ContentElement tree
- **Output**: Transformed ContentElement tree (or subtree)
- **Query param**: `?elementId=xyz` for partial rendering
- **Operations**: Read-only extraction/filtering, no mutations
- **No database**: All data already loaded during hydration
- **Extension**: Future tagged service pattern (similar to Refinery)
- **Common use**: AJAX updates, lazy loading, reduced payloads
