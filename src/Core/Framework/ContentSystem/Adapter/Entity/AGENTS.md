> Conceptual overview and design rationale live in the parent directory's
> [README.md](../README.md). The references and constraints below cover most code
> changes; read the README when you need the mental model.

## Source Code References

**Abstract Base Classes (entity/definition/collection concrete classes co-located with their domain entities):**
- `AbstractContentLayoutAssignableDefinition` - Base definition with shared fields (`id`, the entity-id FK, `sales_channel_id`, `content_layout_id`) plus the override contract every concrete assignment definition implements:
  - `getContentLayoutEntityType(): non-empty-string` — **abstract**; the entity type name that drives field derivation, path prefix, route pattern, data requirements, and resolution. Every concrete definition must override it.
  - `getCacheTags(string $entityId): list<string>` — **abstract**; tags added to the cache context at the start of rendering so the response is invalidated when the context entity changes.
  - `defineEntityIdField(): IdField` — **abstract** (`protected`); the entity-specific ID field (e.g. `product_id`).
  - `getPageDataRequirements(): array<DataRequirement>` — returns one `DataRequirement` for `EntityLoader::SOURCE`, built from the entity type, entity-id field, and `getEntityAssociations()`; consumed by `FactoryHelper/EntityLayoutContextFactory::providedRootContext()` (via `Diagnostics/RootContextMapper::map()`) to derive root-ambient context.
  - `getContentLayoutPathPrefix()` / `getContentLayoutRoutePattern()` — derived from the entity type via `Helper/ContentLayoutMetadataDeriver`; used for request routing and entity-ID extraction from the path.
  - `getContentLayoutEntityIdField(): non-empty-string` — the assignment-table field name identifying the assigned entity.
  - `getEntityAssociations(): list<non-empty-string>` — (`protected`); overridable hook returning association paths eager-loaded for the page entity (default `[]`). Concrete definitions override it to declare eager-loaded associations (e.g. `ProductContentLayoutDefinition`, `CategoryContentLayoutDefinition`); consumed by `getPageDataRequirements()`.
- `AbstractContentLayoutAssignmentEntity` - Base entity with shared properties (salesChannel, contentLayout)

**Concrete implementations (co-located with domain aggregates):**
- `Content/Product/Aggregate/ProductContentLayout/` - Product entity-based assignments
- `Content/Category/Aggregate/CategoryContentLayout/` - Category entity-based assignments
- `Content/LandingPage/Aggregate/LandingPageContentLayout/` - Landing page entity-based assignments

**Storefront domain-scoped assignments (in `Storefront/ContentSystem/`, not a DAL aggregate):**
- `Storefront/ContentSystem/HeaderContentLayout/` - Domain-aware header assignments
- `Storefront/ContentSystem/FooterContentLayout/` - Domain-aware footer assignments

Their `*Entity` classes extend `AbstractContentLayoutAssignmentEntity`, but their `*Definition` classes extend `EntityDefinition` directly (NOT `AbstractContentLayoutAssignableDefinition`); see [Storefront ContentSystem](../../../../../Storefront/ContentSystem/AGENTS.md).

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
