@README.md

## Source Code References

- `HeaderFooterAssignmentWriteValidator` — `kernel.event_subscriber` on `PreWriteValidationEvent`; binding gate for `header_content_layout` and `footer_content_layout` assignment writes; respects `LayoutResolvabilityValidator::SKIP_VALIDATION_STATE` (`'content-system-skip-layout-validation'`); delegates load-and-check to Core's `LayoutBindingGate`; surfaces violations by adding a `WriteConstraintViolationException` to `PreWriteValidationEvent::getExceptions()` rather than throwing
- `HeaderFooterBindingEnumerator` — `content_system.layout_binding_enumerator` tag; enumerates header and footer bindings of a content layout; each binding carries an empty provided root context (both sections expose no root-ambient context)

## Constraints

- Both classes are `@internal` and `#[Package('framework')]`
- `SKIP_VALIDATION_STATE` suppresses assignment validation on both sections when added to the write `Context` via `Context::addState`; intended for trusted bulk importers (no in-repo path sets it); the Storefront validator checks the flag identically to the Core `ContentLayoutAssignmentWriteValidator`
- Per-section enforcement seam: after the `SKIP_VALIDATION_STATE` check, `preValidate()` skips a section's binding check when `LayoutResolvabilityValidator::isBindingEnforced(new BoundRootContext($section, []))` returns `false` — section-level enforcement can be disabled independently of the global state flag
- Each binding carries `new BoundRootContext($section, [])` — empty root context because header/footer sections have no page-data context; a layout bound here must be fully resolvable without any provided root context
- Reverse dependency direction: no Core → Storefront dependency; Core's `LayoutBindingEnumerator` contract is tagged from Storefront with no callback into Storefront
- DI config: `Storefront/DependencyInjection/content-system.xml`
