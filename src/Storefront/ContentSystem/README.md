# Storefront ContentSystem

Header and footer content layout assignments for the Storefront. These are Storefront-only sections — the Core content system (`Core/Framework/ContentSystem/`) has no knowledge of them.

Nothing in the Storefront's own rendering path reads these assignments. A page receives its header and footer as ESI sub-requests to the `frontend.header` and `frontend.footer` routes, both handled in [NavigationController](../Controller/NavigationController.php) and both loading their data through `HeaderPageletLoaderInterface` / `FooterPageletLoaderInterface`; no class under `Storefront/Pagelet/` touches the content system. The header action does branch on its template — a content page merges an `isNewContentStructure` flag into the ESI query parameters, and the route then renders a content-specific header template that still displays the legacy pagelet — whereas the footer action renders the legacy template unconditionally.

## Structure

- **HeaderContentLayout/** — Header assignment entity + domain-aware specification source
- **FooterContentLayout/** — Footer assignment entity + domain-aware specification source
- **Extension/** — Entity extensions adding header/footer associations to `ContentLayout`, `SalesChannel`, and `SalesChannelDomain`
- **Validation/** — [Validation/README.md](Validation/README.md) — DAL `PreWriteValidationEvent` gate for header/footer assignment writes (`HeaderFooterAssignmentWriteValidator`): a tree-blind type-match of the bound layout's immutable `root_source` against the section id
- [docs/header-footer.md](docs/header-footer.md) — The Store API header and footer endpoints, the assignment record, and domain-aware resolution

## Resolution

Domain-aware three-tier fallback via `Core/Framework/ContentSystem/Adapter/FactoryHelper/DomainAwareLayoutResolver`: domain+channel → channel → global.

## DI Config

`Storefront/DependencyInjection/content-system.php` — Registers entity definitions, extensions, specification sources (`content_system.specification_source` tag), section resolvers (`header`, `footer`), and the Validation service (`HeaderFooterAssignmentWriteValidator` with `kernel.event_subscriber`).
