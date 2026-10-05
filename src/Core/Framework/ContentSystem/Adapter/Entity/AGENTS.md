## Constraints

- Entity assignments: `UNIQUE (entity_id, sales_channel_id)` — one global + one per channel per entity
- Header/Footer: `UNIQUE (domain_id, sales_channel_id)` — Storefront-only, registered in `Storefront/DependencyInjection/content-system.php`
- Entity definitions registered in their owning domain's DI, not in `content-system.php`
- Assignments are unidirectional — parent entities have no awareness of ContentSystem
- Entity fallback: sales channel specific → global (null)
- Header/footer fallback: domain+channel → channel → global

## Quick Reference

- Repositories: `{entity}_content_layout.repository` (Core), `header_content_layout.repository` / `footer_content_layout.repository` (Storefront)
- Resolution: see `FactoryHelper/EntityLayoutResolver` and `FactoryHelper/DomainAwareLayoutResolver`
- Package: `#[Package('framework')]`
