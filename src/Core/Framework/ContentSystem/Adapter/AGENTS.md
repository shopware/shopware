@README.md

## Source Code References

- `AbstractSpecificationSource` - Base: `supports()`, `resolveLayoutId()`, `resolveSpecificationData()`, `resolveTargetElementId()`, `resolveCacheTags()`, `supportsEntityType()` (default `false`), `resolveSpecificationDataForEntity()` (default throws `unknownEntityType`) — last two overridden by entity sources for assignment-free resolution
- `RenderingSpecificationResolver` - `resolve()` iterates sources via `supports()` → `RenderingSpecificationFactory::create()`; `resolveWithoutLayout(entityType, entityId, …)` selects via `supportsEntityType()` → `createWithoutLayout()`, throws `unknownEntityType` on no match
- `RenderingSpecificationFactory` - `create()` assembles `ResolvedContentLayout` (layout ID plus `RenderingSpecification`); `createWithoutLayout()` assembles a bare `RenderingSpecification` (no layout id, no assignment lookup) for the preview action
- Entity sources co-located with domain aggregates: `Content/Product/.../ProductSpecificationSource`, `Content/Category/.../CategorySpecificationSource`, `Content/LandingPage/.../LandingPageSpecificationSource`
- Domain-aware sources in Storefront: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`
- `EntityLayoutResolver`, `EntityLayoutContextFactory` (FactoryHelper/) - Shared entity resolution
- `DomainAwareLayoutResolver`, `NavigationAliasResolver` (FactoryHelper/) - Header/footer resolution

## Constraints

- Sources use `supports()` bool method — NOT null-return pattern
- Entity sources tagged `content_system.context_factory` priority 100 — higher priority runs first
- Header/footer sources are NOT in the tagged iterator — injected directly into separate resolver instances
- 3 resolver instances: main (Core, tagged iterator), header + footer (Storefront, single source each)
- Entity query: `WHERE entity_id = X AND (sales_channel_id = Y OR IS NULL) ORDER BY sales_channel_id DESC LIMIT 1`
- Header/footer query: `WHERE (domain_id = X OR IS NULL) AND (sales_channel_id = Y OR IS NULL) ORDER BY domain_id DESC, sales_channel_id DESC LIMIT 1`
