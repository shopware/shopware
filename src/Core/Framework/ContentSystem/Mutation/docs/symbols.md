# Mutation Symbols

The symbol index for the mutation system: each class, its role, and its path. The constraints that
govern editing them stay in [../AGENTS.md](../AGENTS.md), and the conceptual model in
[../README.md](../README.md).

- `LayoutMutation` - The operation contract, `@internal`. `apply(StoredTree $tree): StoredTree` is a pure transform returning a NEW tree; `affected()`, `created()`, `orphaned()`, `droppedWiring()`, `droppedProperties()` report the change computed during `apply()`.
- `AbstractLayoutMutation` - `@internal` base implementing `LayoutMutation`: what `StoredTree` does not carry. Holds the protected `$affected` / `$created` / `$orphaned` / `$droppedWiring` / `$droppedProperties` stash plus their getters, and the element-level primitives the ops share ([shared-primitives.md](shared-primitives.md)). `$created` defaults to the empty list, so an op that mints no node needs no assignment. No tree edits of its own: `find`, `remove`, `insertAtRoot`, `insertIntoSlot` and `replace` are `Layout/StoredTree`'s and the ops call them there.
- `ElementLocation` - `@internal final readonly`. `public StoredElement $node`, `public int $index`, `public ?ParentSlot $parent = null`. A `null` parent marks a root element; `$index` is then the root-list index.
- `ParentSlot` - `@internal final readonly`. `public string $parentId`, `public string $slot`.
- `MutationResult` - `@internal final readonly`, private constructor. Two named constructors, `fromAnalyzedMutation()` (the single owner of result assembly) and `fromParts()`: [runners.md](runners.md).
- `MutationPipeline` - `@internal`, `@final` annotation. The stateless runner over an already-decoded tree: apply, diagnose, mirror onto `created()`, re-diagnose only when the wiring changed the tree, assemble. Never persists. Instance-identity gate: [runners.md](runners.md).
- `ContextConsumerMirror` - `@internal`, `@final` annotation. Mirrors onto the created elements the `acceptsContext` consumers their own resolutions prove, and nothing else. Match rules and the four skips: [consumer-mirroring.md](consumer-mirroring.md).
- `PersistedLayoutMutator` - `@internal`, `@final` annotation. Commits one mutation to a stored `content_layout` under a named lock and an optimistic `updatedAt` token; runs no mirroring. Codes and interim limitations: [runners.md](runners.md).
- `Op/` - The operations, all extending `AbstractLayoutMutation`: `InsertElement`, `RemoveElement`, `MoveElement`, `ReplaceElement`, `DuplicateElement`, `WrapElements`, `UnwrapElement`, `AttachElement`, `BindElement`, `UpdateElementProperties`. Per-op contracts: [operations.md](operations.md).
