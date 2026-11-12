# Shared Primitives

The element-level helpers `AbstractLayoutMutation` gives every operation, and what each one is for. The tree algebra
itself is not here: it belongs to `Layout/StoredTree` and the ops call it there.

- `locate()` - the typed view of `StoredTree::locate()`: the same coordinates as objects, no rule of its own.
- `subtreeIds()` - the node id plus every descendant id, via a throwaway one-node `StoredTree`.
- `cloneWithNewIds()` - deep clone replacing every id in the subtree.
- `scaffoldElement()` - a fresh element seeded with the type's stored defaults (via `storedDefaults`), no wiring.
- `storedDefaults()` - the type's defaults keyed by property key and wrapped for storage. Delegates to the single
  per-type rule `Layout/Type/StoredDefaultProvider::forType`, shared with
  `scaffoldElement`, `Op/ReplaceElement`, and the write-boundary `Layout/LayoutDefaultSeeder`. A translatable
  property's default arrives as a language map under the system language.
- `requireRegistered()` - throws `ContentSystemException::mutationUnknownType` when the type is unregistered.
- `rejectNonLanguageKeys()` - throws `ContentSystemException::mutationPropertyLanguageKeyInvalid` for the first key of
  a translatable property's language map that is not a lowercase hex UUID, as `PropertyType::nonLanguageKeys()`
  reports it, the same rule the DAL write path applies.
  Shared by `Op/UpdateElementProperties` and `Op/TranslateElement`.
- `decodePropertyValue()` - wraps a client-supplied property value with `StoredValue::fromDecoded()`. A client-defect
  `ContentSystemException` (a non-finite float) becomes `mutationPropertyValueRejected`; any other throwable
  passes through. Used by `Op/UpdateElementProperties` and `Op/TranslateElement`.
- `resolveDefaultSpecification()` - the type's default binding specification: zero is `null`, one is returned, more
  than one throws `bindingSpecificationDefaultAmbiguous`.
- `childList()` - every direct child across all slots, in slot order.
