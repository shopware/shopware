# Entity

@README.md

## Source Code References

- `ContentLayoutEntity` - Layout template entity
- `ContentLayoutDefinition` - Layout DAL definition
- `ContentLayoutAssignmentEntity` - Assignment mapping entity
- `ContentLayoutAssignmentDefinition` - Assignment DAL definition
- `ContentLayoutCollection` - Layout collection

## Assignment Entity Structure

Null values indicate wildcards:

```php
// Specific assignment
$assignment->setEntityType('product');
$assignment->setEntityId($productId);
$assignment->setSalesChannelId($scId);

// Default layout (wildcard)
$assignment->setEntityType(null);
$assignment->setEntityId(null);
$assignment->setSalesChannelId($scId);

// Global default
$assignment->setEntityType(null);
$assignment->setEntityId(null);
$assignment->setSalesChannelId(null);
```

## Quick Reference

- **Pair pattern**: Entity (data) + Definition (schema)
- **Collection**: Extends EntityCollection with type hint
- **Two entities**: ContentLayoutEntity (template) + ContentLayoutAssignmentEntity (mapping)
- **ID generation**: Always use Uuid::randomHex()
- **Repository**: Access via 'content_layout.repository' or 'content_layout_assignment.repository'
- **Serialization**: Automatic via custom field serializers
- **Sales channel**: Include in assignments for dimensionality, null for global
- **Package**: `#[Package('discovery')]` on all entities
