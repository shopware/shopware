@README.md

## Source Code References

- `AbstractSpecificationSource` - Base: `supports()`, `resolveLayoutId()`, `resolveSpecificationData()`, `resolveTargetElementId()`, `resolveCacheTags()`
- `RenderingSpecificationResolver` - Iterates sources, checks `supports()`, calls `RenderingSpecificationFactory::create()`
- `RenderingSpecificationFactory` - Assembles `RenderingSpecification` from source steps
- `EntityLayoutResolver`, `EntityLayoutContextFactory` (FactoryHelper/) - Shared entity resolution
- `DomainAwareLayoutResolver`, `NavigationAliasResolver` (FactoryHelper/) - Header/footer resolution

## Constraints

- Sources use `supports()` bool method — NOT null-return pattern
- Entity sources tagged `content_system.context_factory` priority 100 — higher priority runs first
- Header/footer sources are NOT in the tagged iterator — injected directly into separate resolver instances
- 3 resolver instances: main (tagged iterator), header (single source), footer (single source)
- Entity query: `WHERE entity_id = X AND (sales_channel_id = Y OR IS NULL) ORDER BY sales_channel_id DESC LIMIT 1`
- Header/footer query: `WHERE (domain_id = X OR IS NULL) AND (sales_channel_id = Y OR IS NULL) ORDER BY domain_id DESC, sales_channel_id DESC LIMIT 1`
