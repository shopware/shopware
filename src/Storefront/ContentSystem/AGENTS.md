@README.md

## Source Code References

- `HeaderContentLayout/HeaderSpecificationSource`, `FooterContentLayout/FooterSpecificationSource` — extend `Core/Framework/ContentSystem/Adapter/AbstractSpecificationSource`
- `HeaderContentLayout/HeaderContentLayoutDefinition`, `FooterContentLayout/FooterContentLayoutDefinition` — standalone definitions (NOT extending `AbstractContentLayoutAssignableDefinition`)
- `Extension/` — `ContentLayoutExtension`, `SalesChannelExtension`, `SalesChannelDomainExtension`
- Resolution logic: `Core/Framework/ContentSystem/Adapter/FactoryHelper/DomainAwareLayoutResolver`

## Constraints

- `UNIQUE (domain_id, sales_channel_id)` — if `domain_id` is set, `sales_channel_id` MUST also be set
- Section resolvers registered here, NOT in Core `content-system.xml`
- Package: `#[Package('framework')]`
- DI config: `Storefront/DependencyInjection/content-system.xml`
