# Router

@README.md

## Source Code References

- `ContentRouter` - Entry point, delegates to builder and matcher
- `RouteCollectionBuilder` - Queries `content_route` table, builds Symfony RouteCollection
- `ContentRouteMatcher` - Wraps Symfony UrlMatcher for pattern matching

## Constraints

### Architecture: Builder + Matcher Delegation

ContentRouter delegates to two components:
1. **RouteCollectionBuilder**: DB → Symfony RouteCollection
2. **ContentRouteMatcher**: RouteCollection → RouteMatchResult

See `ContentRouter::match()` for implementation.

## Pattern Matching Details

### Priority vs Specificity

**Specificity** (Symfony standard):
- Static segments > dynamic segments
- `/product/special` > `/product/{slug}`

**Priority** (ContentSystem addition):
- Custom tie-breaker field
- Higher value wins when specificity equal

Combined logic:
1. Specificity (Symfony)
2. Priority (ContentSystem) if specificity equal
3. Non-deterministic if both equal (DON'T DO THIS)

## Quick Reference

- **Architecture**: Builder (DB → collection) + Matcher (collection → result)
- **Priority**: DESC order, higher wins
- **Match result**: RouteMatchResult or null (not exception)
- **Sales channel**: Filtered during collection build
