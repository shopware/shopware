## Navigation

- Why these constraints hold, and what was not chosen: [data-loading.md](../docs/principles/data-loading.md)

## Constraints

- Sources use `supports()` bool method — NOT null-return pattern
- Entity sources tagged `content_system.entity_specification_source` priority 100 — higher priority runs first
- Header/footer sources are NOT in the tagged iterator — injected directly into separate resolver instances
- 3 resolver instances: main (Core, tagged iterator), header + footer (Storefront, single source each)
- Entity query: `WHERE entity_id = X AND (sales_channel_id = Y OR IS NULL) ORDER BY sales_channel_id DESC LIMIT 1`
- Header/footer query: `WHERE (domain_id = X AND sales_channel_id = Y) OR (domain_id IS NULL AND sales_channel_id = Y) OR (domain_id IS NULL AND sales_channel_id IS NULL) ORDER BY domain_id DESC, sales_channel_id DESC LIMIT 1`. Three explicit tiers (domain+channel, then channel, then global); there is NO domain-only tier (`domain_id = X AND sales_channel_id IS NULL` never matches)
- Never let a request parameter choose the entity a loader targets or the property it reads. A per-request value reaches a loader only through a declared property. Check: can a query parameter named like the entity id field replace the server-derived placeholder value?
