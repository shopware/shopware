# Output

Post-hydration content transformation. Operates on fully hydrated ContentElement trees — read-only extraction and formatting, no database queries.

## Response Formats

`ContentPage` itself is the full format: complete element trees with hydrated data. It provides lazy transformations to alternative formats:
- `getContentDecomposedPage()` → Skeletons + deduplicated data + assignments
- `getContentSkeletonPage()` → Element trees without hydrated data
- `getContentDataPage()` → Data + assignments without skeleton

Routes return `ContentPage` directly or transform via format-specific `AbstractResponseFactory` implementations.

## Partial Rendering

Extracts specific element subtree via `?elementId` parameter. `SubTreeExtractor` searches roots sequentially — first match returned. Same elementId in multiple roots returns only first occurrence. Pruning keeps context-dependent ancestors during hydration so data still flows correctly to the target; extraction then drops those ancestors and returns only the target subtree.

Header and footer sources never resolve a target element, so those sections never support partial rendering.

## Subdirectories

- **Struct/** - Response data structures (ContentPage, ContentDecomposedPage, ContentSkeletonPage, ContentDataPage)
- **Format/** - Response factory implementations (Full, Decomposed, Skeleton, Data)
