# Entity

@README.md

## Source Code References

- `ContentLayoutEntity` - Layout template entity
- `ContentLayoutDefinition` - Layout DAL definition
- `ContentLayoutCollection` - Layout collection

## Quick Reference

- **Pair pattern**: Entity (data) + Definition (schema)
- **Collection**: Extends EntityCollection with type hint
- **ID generation**: Always use Uuid::randomHex()
- **Repository**: Access via 'content_layout.repository'
- **Serialization**: Automatic via custom field serializers
- **Package**: `#[Package('discovery')]` on all entities
