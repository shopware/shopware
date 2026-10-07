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
  write-boundary `Layout/LayoutDefaultSeeder`, so a type's stored defaults are defined once. Each value arrives in the
  shape storage holds rather than as the declared scalar, so a translatable property's default is a single-entry
  language map under the anchor language (`PropertyType::storedDefault()`) and no scaffold produces a bare value for
  one.
- `requireRegistered(registry, string $type): void` - throws `ContentSystemException::mutationUnknownType` when the
  type is unregistered.
- `rejectNonLanguageKeys(string $elementId, string $key, StoredValue $value): void` - throws
  `ContentSystemException::mutationPropertyLanguageKeyInvalid` for the first language-map key that is not a lowercase
  hex UUID, the key rule the DAL write path enforces. Shared by `Op/UpdateElementProperties` and `Op/TranslateElement`.
- `resolveDefaultSpecification(bindingRegistry, string $type): ?BindingSpecification` - the type's default binding
  specification: zero is `null`, one is returned, more than one throws `bindingSpecificationDefaultAmbiguous`.
- `childList(StoredElement $node): list<StoredElement>` - every direct child across all slots, in slot order.
