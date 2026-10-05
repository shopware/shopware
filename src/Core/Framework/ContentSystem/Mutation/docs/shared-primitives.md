# Shared Primitives

The element-level helpers `AbstractLayoutMutation` gives every operation, and what each one is for. The tree algebra
and the ops call them there.

- `locate()` - the typed view of `StoredTree::locate()`: the same
  coordinates as objects, no rule of its own.
- `subtreeIds()` - the node id plus every descendant id, via a throwaway one-node
  `StoredTree`.
- `cloneWithNewIds()` - deep clone replacing every id in the subtree.
- `scaffoldElement()` -
  a fresh element seeded with the type's primitive property defaults (via `primitiveDefaults`), no wiring.
- `primitiveDefaults()` -
  the type's non-null primitive property defaults keyed by property key, wrapped for storage. Delegates to the single
  per-type rule `Layout/Type/PrimitiveDefaultProvider::forType`, shared with `scaffoldElement`, `Op/ReplaceElement`,
  and the write-boundary `Layout/LayoutDefaultSeeder`.
- `requireRegistered()` - throws `ContentSystemException::mutationUnknownType` when the
  type is unregistered.
- `resolveDefaultSpecification()` - the type's default binding
  specification: zero is `null`, one is returned, more than one throws `bindingSpecificationDefaultAmbiguous`.
- `childList()` - every direct child across all slots, in slot order.
