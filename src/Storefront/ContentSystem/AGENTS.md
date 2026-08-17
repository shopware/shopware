> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Source Code References

- `HeaderContentLayout/HeaderSpecificationSource`, `FooterContentLayout/FooterSpecificationSource` — extend `Core/Framework/ContentSystem/Adapter/AbstractSpecificationSource`
- `HeaderContentLayout/HeaderContentLayoutDefinition`, `FooterContentLayout/FooterContentLayoutDefinition` — standalone definitions (NOT extending `AbstractContentLayoutAssignableDefinition`)
- `Extension/` — `ContentLayoutExtension`, `SalesChannelExtension`, `SalesChannelDomainExtension`
- Resolution logic: `Core/Framework/ContentSystem/Adapter/FactoryHelper/DomainAwareLayoutResolver`
- `Validation/HeaderFooterAssignmentWriteValidator` — `kernel.event_subscriber` on `PreWriteValidationEvent`; the same tree-blind type-match as Core's entity-assignment validator, against the section id (`header` / `footer`) instead of an entity type. It reads the bound layout's immutable `root_source` via Core's shared `Validation/LayoutRootSourceReader` and rejects the write (`ContentSystemException::rootSourceAssignmentMismatch`, 400) when it does not equal the section id; skipped when `LayoutGate::SKIP_VALIDATION_STATE` is set

## Constraints

- `UNIQUE (domain_id, sales_channel_id)` — if `domain_id` is set, `sales_channel_id` MUST also be set; enforced at the DB level by a `CHECK (domain_id IS NULL OR sales_channel_id IS NOT NULL)` constraint (`chk.header_content_layout.domain_requires_channel` / `chk.footer_content_layout.domain_requires_channel`), not only by DAL validation
- Section resolvers registered here, NOT in Core `content-system.php`
- `HeaderSpecificationSource` and `FooterSpecificationSource` carry the `content_system.specification_source` tag (section `header` / `footer`) — added in commit cf2cc8d for the diagnose route's section branch
- Package: `#[Package('framework')]`
- DI config: `Storefront/DependencyInjection/content-system.php`
