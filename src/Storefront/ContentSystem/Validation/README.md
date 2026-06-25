# Validation

Storefront DAL `PreWriteValidationEvent` gate for header/footer content layout assignments. Mirrors the Core `Validation/` gate but scoped to the two Storefront-only sections.

## Key Classes

- `HeaderFooterAssignmentWriteValidator` — the Storefront counterpart of Core's `ContentLayoutAssignmentWriteValidator`: a tree-blind type-match for header/footer assignment writes. It reads the bound layout's immutable `root_source` via Core's shared `LayoutRootSourceReader` and rejects the write (`ContentSystemException::rootSourceAssignmentMismatch`, 400) when it does not equal the section id (`header` / `footer`). Skipped when `SKIP_VALIDATION_STATE` is set
