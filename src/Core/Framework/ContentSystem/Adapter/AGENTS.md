> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Source Code References

- `AbstractSpecificationSource` - Base: `supports()`, `resolveLayoutId()`, `resolveSpecificationData()`, `resolveTargetElementId()`, `resolveCacheTags()`, `supportsEntityType()` (default `false`), `resolveSpecificationDataForEntity()` (default throws `entityTypeResolutionUnsupported`), `providedRootContext(Context $context): list<ProvidedContext>` (default `[]`) — last three overridden by entity sources; `providedRootContext()` is reached via `RootSourceRegistry::resolve()` → `sourceFor($rootSource)`, the single resolution path the write gate, the diagnose route, and the mutation routes all go through
- `RenderingSpecificationResolver` - `resolve()` iterates sources via `supports()` → `RenderingSpecificationFactory::create()`; `resolveWithoutLayout(entityType, entityId, …)` selects via `supportsEntityType()` → `createWithoutLayout()`, throws `unknownEntityType` on no match
- `RenderingSpecificationFactory` - `create()` assembles `ResolvedContentLayout` (layout ID plus `RenderingSpecification`); `createWithoutLayout()` assembles a bare `RenderingSpecification` (no layout id, no assignment lookup) for the preview action
- Entity sources co-located with domain aggregates: `Content/Product/.../ProductSpecificationSource`, `Content/Category/.../CategorySpecificationSource`, `Content/LandingPage/.../LandingPageSpecificationSource`
- Domain-aware sources in Storefront: `Storefront/ContentSystem/HeaderContentLayout/HeaderSpecificationSource`, `Storefront/ContentSystem/FooterContentLayout/FooterSpecificationSource`
- `EntityLayoutResolver`, `EntityLayoutContextFactory` (FactoryHelper/) - Shared entity resolution; `EntityLayoutContextFactory::providedRootContext(AbstractContentLayoutAssignableDefinition $definition): list<ProvidedContext>` delegates to `RootContextMapper::map($definition->getPageDataRequirements())`. `buildSpecificationData(string $entityId, Request $request, SalesChannelContext $context, AbstractContentLayoutAssignableDefinition $definition): SpecificationData` is the assignment-free assembly entry point — all three entity sources call it from `resolveSpecificationDataForEntity()`; the assignment-based `resolveSpecificationData(string $path, …)` extracts the entity id from the path and delegates to it
- `DomainAwareLayoutResolver`, `NavigationAliasResolver` (FactoryHelper/) - Header/footer resolution

## Constraints

- Sources use `supports()` bool method — NOT null-return pattern
- Entity sources tagged `content_system.entity_specification_source` priority 100 — higher priority runs first
- Header/footer sources are NOT in the tagged iterator — injected directly into separate resolver instances
- 3 resolver instances: main (Core, tagged iterator), header + footer (Storefront, single source each)
- Entity query: `WHERE entity_id = X AND (sales_channel_id = Y OR IS NULL) ORDER BY sales_channel_id DESC LIMIT 1`
- Header/footer query: `WHERE (domain_id = X AND sales_channel_id = Y) OR (domain_id IS NULL AND sales_channel_id = Y) OR (domain_id IS NULL AND sales_channel_id IS NULL) ORDER BY domain_id DESC, sales_channel_id DESC LIMIT 1`. Three explicit tiers (domain+channel, then channel, then global); there is NO domain-only tier (`domain_id = X AND sales_channel_id IS NULL` never matches)
