## Navigation

- Why the root source is immutable and is set by the creating write: [stored-model.md](../../../Core/Framework/ContentSystem/docs/principles/stored-model.md)

## Constraints

- `SKIP_VALIDATION_STATE` suppresses assignment validation on both sections when added to the write `Context` via `Context::addState`; intended for trusted bulk importers (no in-repo path sets it); the Storefront validator checks the flag identically to the Core `ContentLayoutAssignmentWriteValidator`
- It never decodes or resolves the layout tree. A `null` root source (layout not loadable) is left to the FK constraint
- No Core → Storefront dependency: the Storefront validator depends on Core's `LayoutRootSourceReader`, with no callback into Storefront
