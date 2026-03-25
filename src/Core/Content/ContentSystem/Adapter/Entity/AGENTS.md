# Entity

@README.md

## Source Code References

**Entity Assignments:**
- `{Product|Category|LandingPage}ContentLayoutDefinition` - Assignment DAL definitions
- `{Product|Category|LandingPage}ContentLayoutEntity` - Assignment entities
- `{Product|Category|LandingPage}ContentLayoutCollection` - Assignment collections
- `ContentLayoutAssignmentInterface` - Entity contract for assignments (getAssignedEntityId, getContentLayoutId, etc)
- `ContentLayoutAssignableDefinitionInterface` - Definition contract (getContentLayoutEntityType, getContentLayoutPathPrefix, etc)

**Header/Footer Assignments:**
- `HeaderContentLayoutDefinition/Entity/Collection` - Header layout assignments
- `FooterContentLayoutDefinition/Entity/Collection` - Footer layout assignments

## Assignment Pattern

Three entity types (Product, Category, LandingPage) with identical structure referencing different parent entities. Assignments owned by ContentSystem via interface contracts - parent entities have no awareness (unidirectional).

## Header/Footer Assignment Pattern

Header/Footer entities do NOT implement `ContentLayoutAssignableDefinitionInterface`. They use domain-aware resolution with `domainId` field instead of entity-based resolution. No parent entity reference - these are singleton assignments per domain/sales channel scope.

## Sales Channel Fallback

Query pattern with priority-based fallback (specific → global):

```php
$criteria->addFilter(new EqualsFilter($entityIdField, $entityId));
$criteria->addFilter(new OrFilter([
    new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
    new EqualsFilter('salesChannelId', null),  // Global
]));
$criteria->addSorting(new FieldSorting('salesChannelId', FieldSorting::DESCENDING));
$criteria->setLimit(1);
```

See `EntityLayoutResolver::resolve()` in `Adapter/FactoryHelper/`.

## Unique Constraints

Each table: `UNIQUE (entity_id, sales_channel_id)`

- One global assignment per entity (entity_id, null)
- One assignment per entity per sales channel (entity_id, sc_id)

## Quick Reference

- **Entity types**: Product/Category/LandingPage assignments (identical structure, different parents)
- **Header/Footer types**: Singleton assignments per domain/sales channel scope
- **Unidirectional**: Parents unaware of ContentSystem, accessed via interfaces
- **Sales channel**: null = global, specific ID = channel-specific
- **Domain (header/footer only)**: null = any domain, specific ID = domain-specific
- **Fallback (entities)**: Specific channel → Global (null)
- **Fallback (header/footer)**: Domain+Channel → Channel → Global
- **Repository**: `{entity}_content_layout.repository`, `header_content_layout.repository`, `footer_content_layout.repository`
- **Package**: `#[Package('discovery')]`
- **Parent reference**: `getParentDefinitionClass()` for DAL only, no OneToMany in parent
- **Header/Footer difference**: No `ContentLayoutAssignableDefinitionInterface`, uses `domainId` field
