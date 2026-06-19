# Storefront ContentSystem

Header and footer content layout assignments for the Storefront. These are Storefront-only sections — the Core content system (`Core/Framework/ContentSystem/`) has no knowledge of them.

## Structure

- **HeaderContentLayout/** — Header assignment entity + domain-aware specification source
- **FooterContentLayout/** — Footer assignment entity + domain-aware specification source
- **Extension/** — Entity extensions adding header/footer associations to `ContentLayout`, `SalesChannel`, and `SalesChannelDomain`
- **Validation/** — DAL `PreWriteValidationEvent` gate for header/footer assignment writes (`HeaderFooterAssignmentWriteValidator`) and binding enumerator for the bound-layout re-check (`HeaderFooterBindingEnumerator`)

## Resolution

Domain-aware three-tier fallback via `Core/Framework/ContentSystem/Adapter/FactoryHelper/DomainAwareLayoutResolver`: domain+channel → channel → global.

## DI Config

`Storefront/DependencyInjection/content-system.xml` — Registers entity definitions, extensions, specification sources (`content_system.specification_source` tag), section resolvers (`header`, `footer`), and the Validation services (`HeaderFooterBindingEnumerator` with `content_system.layout_binding_enumerator` tag; `HeaderFooterAssignmentWriteValidator` with `kernel.event_subscriber`).
