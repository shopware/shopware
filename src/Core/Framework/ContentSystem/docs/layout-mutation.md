# Layout Mutation

The module-root view of structural layout edits: the two runners, the operations they drive, and what
they guarantee about content nothing else can recover.

- **Layout mutation**: `Mutation/MutationPipeline` is the stateless runner over an already-decoded tree, driving `Mutation/Op` operations (`InsertElement`, `RemoveElement`, `MoveElement`, `ReplaceElement`, `DuplicateElement`, `WrapElements`, `UnwrapElement`, `AttachElement`, `BindElement`, `UpdateElementProperties`), all extending `Mutation/AbstractLayoutMutation` and editing through `Layout/StoredTree`'s own immutable tree surgery. `Mutation/PersistedLayoutMutator` is the persisted counterpart, committing one operation to a stored `content_layout` through the resolvability gates. No operation ever silently loses content — detached subtrees come back via `orphaned()`, dropped wiring via `droppedWiring()`, uncarryable values via `droppedProperties()`. Runner contracts, per-op rules and every error code are owned by [Mutation/AGENTS.md](../Mutation/AGENTS.md) and its `../Mutation/docs/`; the two controllers exposing them by [Api/AGENTS.md](../Api/AGENTS.md)
