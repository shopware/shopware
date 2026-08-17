> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Source Code References

- `HeaderFooterAssignmentWriteValidator` — `kernel.event_subscriber` on `PreWriteValidationEvent`; the tree-blind type-match for `header_content_layout` and `footer_content_layout` assignment writes. It reads the bound layout's immutable `root_source` via Core's shared `Validation/LayoutRootSourceReader` (in-flight write batch first, then committed) and rejects the write when it does not equal the section id (`SECTION_BY_ENTITY` maps each assignment entity to `ContentSection::HEADER->value` / `FOOTER->value`); respects `LayoutGate::SKIP_VALIDATION_STATE` (`'content-system-skip-layout-validation'`); surfaces a `ContentSystemException::rootSourceAssignmentMismatch` (400) by adding a `WriteConstraintViolationException` to `PreWriteValidationEvent::getExceptions()` rather than throwing

## Constraints

- The class is `@internal` and `#[Package('framework')]`
- `SKIP_VALIDATION_STATE` suppresses assignment validation on both sections when added to the write `Context` via `Context::addState`; intended for trusted bulk importers (no in-repo path sets it); the Storefront validator checks the flag identically to the Core `ContentLayoutAssignmentWriteValidator`
- It never decodes or resolves the layout tree: resolvability is already enforced at the `content_layout` write and `root_source` is immutable, so a pure type-match against the section id suffices. A `null` root source (layout not loadable) is left to the FK constraint
- Reverse dependency direction: no Core → Storefront dependency; the Storefront validator depends on Core's `LayoutRootSourceReader`, with no callback into Storefront
- DI config: `Storefront/DependencyInjection/content-system.php`
