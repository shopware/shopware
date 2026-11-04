# Validation

Storefront DAL `PreWriteValidationEvent` gate for header/footer content layout assignments. Mirrors the Core `Validation/` gate but scoped to the two Storefront-only sections.

## Key Classes

- `HeaderFooterAssignmentWriteValidator`: the Storefront counterpart of Core's `ContentLayoutAssignmentWriteValidator`: a tree-blind type-match for header/footer assignment writes. It reads the bound layout's immutable `root_source` via Core's shared `LayoutRootSourceReader`. A `null` root source is left to the FK constraint. Skipped when `SKIP_VALIDATION_STATE` is added to the write `Context` via `Context::addState` (trusted bulk importers; no in-repo path sets it). See [The creating write sets the root source of a layout](../../../Core/Framework/ContentSystem/docs/principles/stored-model.md#the-creating-write-sets-the-root-source-of-a-layout)
