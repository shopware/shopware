# Adapter

@README.md

## Source Code References

- `AbstractRenderingSpecificationFactory` - Factory base class (root ContentSystem/)
- `ProductContentLayoutContextFactory` - Product entity factory
- `CategoryContentLayoutContextFactory` - Category entity factory
- `LandingPageContentLayoutContextFactory` - Landing page entity factory
- `LayoutSearchHelper` - Shared query logic

## Constraints

### Chain of Responsibility Contract

Factory must:
- Return `RenderingSpecification` if it can handle the path
- Return `null` if it cannot handle (pass to next factory)
- Throw exception ONLY when path matches pattern but processing fails

**Critical:** Return null on no match, not exception. Exceptions block chain.

### LayoutSearchHelper Query

Single query with OR filter:
```
WHERE entity_id = X AND (sales_channel_id = Y OR sales_channel_id IS NULL)
ORDER BY sales_channel_id DESC
LIMIT 1
```

Returns first match (channel-specific), second match (global), or null.

### DI Priority Requirement

Entity factories should have priority 100. Higher priority factories run first in the Chain of Responsibility pattern.

## Quick Reference

- **Pattern**: Return null to pass, RenderingSpecification to claim
- **Priority**: Higher runs first (entity factories at 100)
- **Database**: Single query with sales channel fallback (specific → global)
- **Exceptions**: Throw only when factory should handle but fails
- **Extension**: Extend abstract class, tag with DI priority > 0
