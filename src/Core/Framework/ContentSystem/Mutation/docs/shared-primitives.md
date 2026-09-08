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
  a fresh element seeded with the type's primitive property defaults (via `primitiveDefaults`), no wiring.
- `primitiveDefaults(AbstractContentSystemElementTypeRegistry $registry, string $type): array<string, StoredValue>` -
  the type's non-null primitive property defaults keyed by property key, wrapped for storage. Delegates to the single
  per-type rule `Layout/Type/PrimitiveDefaultProvider::forType`, shared with `scaffoldElement`, `Op/ReplaceElement`,
  and the write-boundary `Layout/LayoutDefaultSeeder`, so "a type's primitive defaults" is defined once.
- `requireRegistered(registry, string $type): void` - throws `ContentSystemException::mutationUnknownType` when the
  type is unregistered.
- `resolveDefaultSpecification(bindingRegistry, string $type): ?BindingSpecification` - the type's default binding
  specification: zero is `null`, one is returned, more than one throws `bindingSpecificationDefaultAmbiguous`.
- `childList(StoredElement $node): list<StoredElement>` - every direct child across all slots, in slot order.
