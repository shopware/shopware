# Entity

@README.md

## Source Code References

- `{Product|Category|LandingPage}ContentLayoutDefinition` - Assignment DAL definitions
- `{Product|Category|LandingPage}ContentLayoutEntity` - Assignment entities
- `{Product|Category|LandingPage}ContentLayoutCollection` - Assignment collections
- `ContentLayoutAssignmentInterface` - Entity contract for assignments (getAssignedEntityId, getContentLayoutId, etc)
- `ContentLayoutAssignableDefinitionInterface` - Definition contract (getContentLayoutEntityType, getContentLayoutPathPrefix, etc)

## Assignment Pattern

Three entity types (Product, Category, LandingPage) with identical structure referencing different parent entities. Assignments owned by ContentSystem via interface contracts - parent entities have no awareness (unidirectional).

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

See `EntityLayoutFinder::findLayoutAssignment()`.

## Unique Constraints

Each table: `UNIQUE (entity_id, sales_channel_id)`

- One global assignment per entity (entity_id, null)
- One assignment per entity per sales channel (entity_id, sc_id)

## Quick Reference

- **Three types**: Product/Category/LandingPage assignments (identical structure, different parents)
- **Unidirectional**: Parents unaware of ContentSystem, accessed via interfaces
- **Sales channel**: null = global, specific ID = channel-specific
- **Fallback**: Specific channel → Global (null)
- **Repository**: `{entity}_content_layout.repository`
- **Package**: `#[Package('discovery')]`
- **Parent reference**: `getParentDefinitionClass()` for DAL only, no OneToMany in parent
