@README.md

## Source Code References

- `HeaderContentLayout/HeaderSpecificationSource`, `FooterContentLayout/FooterSpecificationSource` — extend `Core/Framework/ContentSystem/Adapter/AbstractSpecificationSource`
- `HeaderContentLayout/HeaderContentLayoutDefinition`, `FooterContentLayout/FooterContentLayoutDefinition` — standalone definitions (NOT extending `AbstractContentLayoutAssignableDefinition`)
- `Extension/` — `ContentLayoutExtension`, `SalesChannelExtension`, `SalesChannelDomainExtension`
- Resolution logic: `Core/Framework/ContentSystem/Adapter/FactoryHelper/DomainAwareLayoutResolver`
- `Validation/HeaderFooterAssignmentWriteValidator` — `kernel.event_subscriber` on `PreWriteValidationEvent`; binding gate for header/footer assignment writes; delegates to Core's `Validation/LayoutBindingGate`; skipped when `LayoutResolvabilityValidator::SKIP_VALIDATION_STATE` is set, or per-section when `LayoutResolvabilityValidator::isBindingEnforced(new BoundRootContext($section, []))` returns `false`
- `Validation/HeaderFooterBindingEnumerator` — `content_system.layout_binding_enumerator`; enumerates header and footer bindings of a layout for Core's bound-layout re-check

## Constraints

- `UNIQUE (domain_id, sales_channel_id)` — if `domain_id` is set, `sales_channel_id` MUST also be set; enforced at the DB level by a `CHECK (domain_id IS NULL OR sales_channel_id IS NOT NULL)` constraint (`chk.header_content_layout.domain_requires_channel` / `chk.footer_content_layout.domain_requires_channel`), not only by DAL validation
- Section resolvers registered here, NOT in Core `content-system.xml`
- `HeaderSpecificationSource` and `FooterSpecificationSource` carry the `content_system.specification_source` tag (section `header` / `footer`) — added in commit cf2cc8d for the diagnose route's section branch
- Package: `#[Package('framework')]`
- DI config: `Storefront/DependencyInjection/content-system.xml`
