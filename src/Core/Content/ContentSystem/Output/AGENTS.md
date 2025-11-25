# Output

@README.md

## Source Code References

- `SubTreeExtractor` - Extracts element sub-tree for partial rendering
- `RenderingSpecification` - Rendering configuration (layout ID, placeholders, target element)
- `ContentPage` (Struct/) - Full format response (elements with embedded properties)
- `DecomposedContentPage` (Struct/) - Decomposed format response (skeletons + data + assignments)

**Terminology clarification for LLMs:** DecomposedContentPage is NOT about partial rendering (that's `?elementId` parameter). It's about response format - decomposed format separates element structure from property data for deduplication.

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

### Multi-Root Extraction

See `ContentRouteLoader::applyPartialRendering()` - iterates roots, returns first match.

**Edge case:** If same elementId exists in multiple roots, only first occurrence returned.

## Quick Reference

- **Pipeline**: After hydration, before response serialization
- **Input**: Fully hydrated ContentElement tree
- **Output**: Transformed ContentElement tree (or subtree)
- **Query param**: `?elementId=xyz` for partial rendering (NOT related to DecomposedContentPage)
- **Response formats**:
  - `ContentPage`: Full format (`/store-api/content/{path}`)
  - `DecomposedContentPage`: Decomposed format (`/store-api/content-decomposed/{path}`)
- **Operations**: Read-only extraction/filtering, no mutations
- **No database**: All data already loaded during hydration
- **Extension**: Via tagged services or event subscribers
- **Common use**: AJAX updates (partial), deduplication (decomposed format)
