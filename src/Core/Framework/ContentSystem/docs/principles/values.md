# Values

This area concerns the value that a layout stores under a property key and who owns the rules for that value. The model is that the server owns every rule about a value.

## Every rule about a value is the server's, and the client sends the raw value

The write gate checks the value that the client sent. The write boundary never sanitizes, repairs, coerces or substitutes that value. Bringing a value into stored shape is the client's job.

- The editor keeps undo local.
- The editor saves the whole layout as it is.
- After saving, the editor reads the result back.
- The editor relays a returned layout verbatim and does not re-derive it.

A client check may gate an interaction. A client check must not coerce, filter or drop a value, and no client-side sanitizer runs before the write boundary.

Why: A rewrite based on a guess hides the client defect. A copy of a rule in the client drifts from the server's rule or becomes a second gate that the server cannot see.

Exceptions: The passes of `LayoutWriteBoundary::apply()` seed declared defaults and normalize style. The multi-entity picker trims unresolvable ids.

In code:

- `sw-experience-studio-detail` re-reads the layout after saving it.
- The `sw-experience-studio-detail` spec pins that the editor adopts the reloaded layout as the server returned it.
- See [layout-write-gates.md](../layout-write-gates.md).

## A rule about a stored value has one owner, and every path that needs the rule calls that owner

Admissibility for a declared property, a default's shape, the nesting bound and a wire message must each have a "single source of truth", its owner. Diagnostics, the conformance validator, the codec and the mutation operations must call that owner. Those callers must keep no copy of the rule. A rule needed at mutation time and at the write boundary is one shared provider.

Why: In an earlier design, diagnostics, the conformance validator and the mutation operations each kept a private match table for the same admission rule. With three tables, the module stores or drops a value that one table accepts and another rejects, depending on the path that the value takes.

Exceptions: A `resolvedBy` storage key is undeclared, and `matchesStoredValueShape()` checks the values that a layout stores under that key. The codec and the constraint descriptor keep separate copies of the wiring rules, under the [rule that one change tightens both](drafts-and-gates.md#the-codec-and-the-constraint-descriptor-keep-separate-copies-of-the-wiring-rules-and-one-change-tightens-both).

In code:

- `StoredElementCodec::MAX_NESTING_DEPTH` owns the nesting bound.
- `StoredTreeShapeConformanceTest` pins that the codec and the descriptor agree on `MAX_NESTING_DEPTH`.
- See [Layout/Field/README.md](../../Layout/Field/README.md).

## A present null and an absent key are different states

A present null and an absent key differ as null and undefined differ in JavaScript, and PHP tells them apart with `array_key_exists`, not with isset. An authored null is a stored value. It survives mutation and blocks default seeding under the [seeding rule](stored-model.md#the-module-seeds-a-primitive-default-at-write-time-never-at-serve-time). A loader that found nothing yields a present null. An unwritten property is absent. No path collapses a present null and an absent key into one state for a property or wiring value.

Why: Collapsing a present null and an absent key is silent degradation. An absent text property would then pass for an authored empty text.

Exceptions: `RenderedElementFactory` drops an authored null and keeps a lookup's null. A template prop with a null default and a type default of null both merge a present null and an absent key.

In code:

- `StoredElement::property()` distinguishes an absent key from an authored null.
- See [listener-api.md](../../Event/Listener/docs/listener-api.md).

## One encoding per meaning: the module stores an empty map as absence

A stored or sent empty object decodes as the empty list. Removing the last entry of a map drops the key. The module does not emit a map-valued element member with no entries.

Why: An empty map and an absent key would be two encodings of one meaning. The wire cannot distinguish an empty map from an empty list.

Exceptions: The module emits an element's `properties` even when that map is empty. The module emits the `slots` of a skeleton or decomposed node even when the node has no slots.

In code:

- `StoredValue::fromDecoded()` reads an empty array as a list.
- `StoredElementCodec::decodeStyle()` rejects an empty breakpoint map.
- `StoredElement::jsonSerialize()` omits every empty member except `properties`.
- `StoredElementCodecStructuralDecodeTest` pins that decode rejects an empty breakpoint map instead of reading it as absent.
- See [Layout/Element/README.md](../../Layout/Element/README.md).
