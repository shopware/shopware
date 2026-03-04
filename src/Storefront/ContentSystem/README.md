# Storefront ContentSystem

Header and footer content layout assignments for the Storefront. These are Storefront-only sections — the Core content system (`Core/Framework/ContentSystem/`) has no knowledge of them.

## Structure

- **HeaderContentLayout/** — Header assignment entity + domain-aware specification source
- **FooterContentLayout/** — Footer assignment entity + domain-aware specification source
- **Extension/** — Entity extensions adding header/footer associations to `ContentLayout`, `SalesChannel`, and `SalesChannelDomain`

## Resolution

Domain-aware three-tier fallback via `Core/Framework/ContentSystem/Adapter/FactoryHelper/DomainAwareLayoutResolver`: domain+channel → channel → global.

## DI Config

`Storefront/DependencyInjection/content-layout.xml` — Registers entity definitions, extensions, specification sources, and section resolvers (`header`, `footer`).
