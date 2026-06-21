# Validation

Storefront DAL `PreWriteValidationEvent` gate for header/footer content layout assignments. Mirrors the Core `Validation/` gate but scoped to the two Storefront-only sections.

## Key Classes

- `HeaderFooterAssignmentWriteValidator` — the Storefront counterpart of Core's `ContentLayoutAssignmentWriteValidator`; blocks assignment writes where the bound layout is not resolvable without page data; delegates to Core's shared `LayoutBindingGate`. Skips a section entirely when `SKIP_VALIDATION_STATE` is set, or per-section when `LayoutResolvabilityValidator::isBindingEnforced(new BoundRootContext($section, []))` returns `false`
- `HeaderFooterBindingEnumerator` — enumerates all header and footer bindings of a layout for the bound-layout re-check in Core's `ContentLayoutWriteValidator`; registered via the `content_system.layout_binding_enumerator` tag
