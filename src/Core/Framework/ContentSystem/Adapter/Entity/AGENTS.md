@.

## Source Code References

**Entity Assignments:**
- `AbstractContentLayoutAssignableDefinition` - Base definition with shared fields
- `AbstractContentLayoutAssignmentEntity` - Base entity with shared properties
- `ContentLayoutAssignmentInterface` - Entity contract (getContentLayoutId, getParameterBindings)
- `{Product|Category|LandingPage}ContentLayout{Definition|Entity|Collection}` - Concrete implementations

**Header/Footer Assignments:**
- `{Header|Footer}ContentLayout{Definition|Entity|Collection}` - Domain-aware assignments (do NOT extend abstract)

## Constraints

- Entity assignments: `UNIQUE (entity_id, sales_channel_id)` — one global + one per channel per entity
- Header/Footer: `UNIQUE (domain_id, sales_channel_id)` — uses domain-aware resolution, no abstract base
- Assignments are unidirectional — parent entities have no awareness of ContentSystem
- Entity fallback: sales channel specific → global (null)
- Header/footer fallback: domain+channel → channel → global

## Quick Reference

- Repositories: `{entity}_content_layout.repository`, `header_content_layout.repository`, `footer_content_layout.repository`
- Resolution: see `FactoryHelper/EntityLayoutResolver` and `FactoryHelper/DomainAwareLayoutResolver`
- Package: `#[Package('framework')]`
