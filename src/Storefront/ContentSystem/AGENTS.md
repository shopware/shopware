## Constraints

- Set `sales_channel_id` whenever `domain_id` is set on a header or footer assignment. Check: does any write bind a domain without a sales channel? See [header-footer.md](docs/header-footer.md#headerfooter-assignment-structure)
- Render a content element only by including `_element.html.twig` with `{ element: element } only`. Name an element's template by its type name. Check: does any template render an element another way? See [clients.md](../../Core/Framework/ContentSystem/docs/principles/clients.md#one-shared-partial-renders-every-element)

## Where to look

- Header and footer Store API endpoints and database tables: [header-footer.md](docs/header-footer.md#header-and-footer-sections)
- Assignment record, its unique key and domain requirement: [header-footer.md](docs/header-footer.md#headerfooter-assignment-structure)
- Domain-aware resolution of the assigned layout: [header-footer.md](docs/header-footer.md#domain-aware-resolution)
- Header and footer placeholders and partial rendering: [header-footer.md](docs/header-footer.md#headerfooter-placeholders)
- Module layout, and how a Storefront page gets its header and footer: [README.md](README.md#storefront-contentsystem)
- Where the header and footer services are registered: [README.md](README.md#di-config)
