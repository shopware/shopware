# Adapter

@README.md

## Source Code References

- `RenderingSpecificationFactoryInterface` - Factory contract (root ContentSystem/)
- `ProductContentLayoutContextFactory` - Product entity factory
- `CategoryContentLayoutContextFactory` - Category entity factory
- `LandingPageContentLayoutContextFactory` - Landing page entity factory
- `LayoutSearchHelper` - Shared query logic
- `RouteBasedContextFactory` - Route-based factory (Routing/)

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

RouteBasedContextFactory must have lowest priority (0). It's catch-all - handles all paths not matched by entity factories. Higher priority entity factories run first.

## Quick Reference

- **Pattern**: Return null to pass, RenderingSpecification to claim
- **Priority**: Higher runs first, RouteBasedContextFactory at 0
- **Database**: Single query with sales channel fallback (specific → global)
- **Exceptions**: Throw only when factory should handle but fails
- **Extension**: Implement interface, tag with DI priority > 0
