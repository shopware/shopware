# Storefront ContentSystem

Header and footer content layout assignments for the Storefront. These are Storefront-only sections — the Core content system (`Core/Framework/ContentSystem/`) has no knowledge of them.

Nothing in the Storefront's own rendering path reads these assignments. A page receives its header and footer as ESI sub-requests to the `frontend.header` and `frontend.footer` routes in [NavigationController](../Controller/NavigationController.php), and no class under `Storefront/Pagelet/` touches the content system. For a content page the header action merges an `isNewContentStructure` flag into the ESI query parameters and renders a content-specific header template that still displays the legacy pagelet. The footer action renders the legacy template unconditionally.

## Structure

- **HeaderContentLayout/** — Header assignment entity + domain-aware specification source
- **FooterContentLayout/** — Footer assignment entity + domain-aware specification source. Both definitions extend `EntityDefinition` directly, not Core's `AbstractContentLayoutAssignableDefinition`, since these sections are not a DAL aggregate assignable by entity type.
- **Extension/** — Entity extensions adding header/footer associations to `ContentLayout`, `SalesChannel`, and `SalesChannelDomain`
- **Validation/** — [Validation/README.md](Validation/README.md) — DAL `PreWriteValidationEvent` gate for header/footer assignment writes (`HeaderFooterAssignmentWriteValidator`): a tree-blind type-match of the bound layout's immutable `root_source` against the section id
- [docs/header-footer.md](docs/header-footer.md) — The Store API header and footer endpoints, the assignment record, and domain-aware resolution

## DI Config

`Storefront/DependencyInjection/content-system.php` registers the header and footer entity definitions, entity extensions, specification sources, section resolvers and the assignment write validator. The Core `content-system.php` does not register them.
