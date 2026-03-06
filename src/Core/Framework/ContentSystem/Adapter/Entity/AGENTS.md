@.

## Source Code References

**Abstract Base Classes (entity/definition/collection concrete classes co-located with their domain entities):**
- `AbstractContentLayoutAssignableDefinition` - Base definition with shared fields
- `AbstractContentLayoutAssignmentEntity` - Base entity with shared properties (salesChannel, contentLayout)

**Concrete implementations (co-located with domain aggregates):**
- `Content/Product/Aggregate/ProductContentLayout/` - Product entity-based assignments
- `Content/Category/Aggregate/CategoryContentLayout/` - Category entity-based assignments
- `Content/LandingPage/Aggregate/LandingPageContentLayout/` - Landing page entity-based assignments
- `Storefront/ContentSystem/HeaderContentLayout/` - Domain-aware header assignments
- `Storefront/ContentSystem/FooterContentLayout/` - Domain-aware footer assignments

## Constraints

- Entity assignments: `UNIQUE (entity_id, sales_channel_id)` — one global + one per channel per entity
- Header/Footer: `UNIQUE (domain_id, sales_channel_id)` — Storefront-only, registered in `Storefront/DependencyInjection/content-system.xml`
- Assignments are unidirectional — parent entities have no awareness of ContentSystem
- Entity fallback: sales channel specific → global (null)
- Header/footer fallback: domain+channel → channel → global

## Quick Reference

- Repositories: `{entity}_content_layout.repository` (Core), `header_content_layout.repository` / `footer_content_layout.repository` (Storefront)
- Resolution: see `FactoryHelper/EntityLayoutResolver` and `FactoryHelper/DomainAwareLayoutResolver`
- Package: `#[Package('framework')]`
