# Output

Post-hydration content transformation. Operates on fully hydrated ContentElement trees — read-only extraction and formatting, no database queries.

## Response Formats

`ContentPage` provides lazy transformations to alternative formats:
- `getContentDecomposedPage()` → Skeletons + deduplicated data + assignments
- `getContentSkeletonPage()` → Element trees without hydrated data
- `getContentDataPage()` → Data + assignments without skeleton

Routes return `ContentPage` directly or transform via format-specific `AbstractResponseFactory` implementations.

## Partial Rendering

Extracts specific element subtree via `?elementId` parameter. `SubTreeExtractor` searches roots sequentially — first match returned. Same elementId in multiple roots returns only first occurrence.

## Subdirectories

- **Struct/** - Response data structures (ContentPage, ContentDecomposedPage, ContentSkeletonPage, ContentDataPage)
- **Format/** - Response factory implementations (Full, Decomposed, Skeleton, Data)
