# Shared Primitives

The element-level helpers `AbstractLayoutMutation` gives every operation, and what each one is for. The tree algebra
itself is not here: `find`, `remove`, `insertAtRoot`, `insertIntoSlot` and `replace` belong to `Layout/StoredTree`
and the ops call them there.

- `locate(StoredTree $tree, string $id): ?ElementLocation` - the typed view of `StoredTree::locate()`: the same
  coordinates as objects, no rule of its own.
- `subtreeIds(StoredElement $node): list<string>` - the node id plus every descendant id, via a throwaway one-node
  `StoredTree`.
- `cloneWithNewIds(StoredElement $node): StoredElement` - deep clone reminting every id in the subtree.
- `scaffoldElement(AbstractContentSystemElementTypeRegistry $registry, string $type, array $slots = []): StoredElement` -
  a fresh element seeded with the type's stored defaults (via `storedDefaults`), no wiring.
- `storedDefaults(AbstractContentSystemElementTypeRegistry $registry, string $type): array<string, StoredValue>` -
  the type's defaults keyed by property key and wrapped for storage. Delegates to the single per-type rule
  `Layout/Type/StoredDefaultProvider::forType`, shared with `scaffoldElement`, `Op/ReplaceElement`, and the
  write-boundary `Layout/LayoutDefaultSeeder`, so a type's stored defaults are defined once.
- `requireRegistered(registry, string $type): void` - throws `ContentSystemException::mutationUnknownType` when the
  type is unregistered.
- `resolveDefaultSpecification(bindingRegistry, string $type): ?BindingSpecification` - the type's default binding
  specification: zero is `null`, one is returned, more than one throws `bindingSpecificationDefaultAmbiguous`.
- `childList(StoredElement $node): list<StoredElement>` - every direct child across all slots, in slot order.
