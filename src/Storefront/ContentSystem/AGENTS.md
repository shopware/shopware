## Navigation

- Why every content element renders through one shared partial, and what was not chosen: [clients.md](../../Core/Framework/ContentSystem/docs/principles/clients.md)

## Constraints

- `UNIQUE (domain_id, sales_channel_id)` — if `domain_id` is set, `sales_channel_id` MUST also be set; enforced at the DB level by a `CHECK (domain_id IS NULL OR sales_channel_id IS NOT NULL)` constraint (`chk.header_content_layout.domain_requires_channel` / `chk.footer_content_layout.domain_requires_channel`), not only by DAL validation
- Section resolvers registered here, NOT in Core `content-system.php`
- `HeaderSpecificationSource` and `FooterSpecificationSource` carry the `content_system.specification_source` tag (section `header` / `footer`)
- Package: `#[Package('framework')]`
- DI config: `Storefront/DependencyInjection/content-system.php`
- Render a content element only by including `_element.html.twig` with `{ element: element } only`. Name an element's template by its type name. Check: does any template render an element another way?
