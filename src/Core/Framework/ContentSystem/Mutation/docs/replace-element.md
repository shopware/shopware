# ReplaceElement

The one operation that changes an element's type in place, and the only one whose contract is a set of carry-over
rules rather than a placement. The other operations are in [operations.md](operations.md).

`__construct(AbstractContentSystemElementTypeRegistry $registry, string $elementId, string $newType, AbstractContentSystemBindingSpecificationRegistry $bindingRegistry, BindingApplicator $bindingApplicator)`.

## What it carries over

Swaps an element's component to `$newType`, keeping the same id. `requireRegistered($newType)`; the element must
exist (`mutationTargetNotFound`); carries over primitive properties whose key and type match, wiring (data
requirements, providers, consumers) keyed to a non-primitive new-type property, and children of slots present in the
new type, then seeds the new type's primitive defaults for any key it does not carry (a carried or authored value
wins).

"Type match" is `Layout/Type/Specification/PropertyType::admits()`, the one conformance predicate the write path and
the diagnostics also read, behind an `isPrimitive()` pre-gate on the new type's declaration. Two consequences follow
from the predicate rather than from any rule of this operation's own. A translatable property carries its whole
language map across, provided both the old and the new type declare that key translatable — a bare string under a
translatable key is not carryable, because `admits()` rejects it. And an authored present `null` under a
non-translatable primitive carries rather than being dropped: `admits()` admits the null variant for every such
declaration, since whether a key may be null is the required-rule's business. The default overlay then leaves that
null in place, because `+` fills only an absent key. The element's `style` carries over unconditionally, being universal and type-independent, and
`attributedSpecifications` survives only for keys whose carried data requirement survives.

A stored property under one of the new type's `resolvedBy` storage keys is likewise carryable: `carryProperties()`
maps the new type's default specification's `resolves` entries whose loader is one of the two built-in resolvedBy
loaders (`Binding/ResolvedByLoaderBranch::fromLoaderSource()`) to their storage keys, and carries a value forward
only when its shape strictly matches that branch (`matchesStoredValueShape()`: a string for `entity`, a list of
strings for `entity_collection`). A shape mismatch is dropped and reported like any other uncarryable value.

## The default overlay

After the rebuild, the new type's default binding specification, when it has exactly one
(`resolveDefaultSpecification()`; zero is a no-op, more than one throws `bindingSpecificationDefaultAmbiguous`
`409`), is fill-applied via `BindingApplicator::applyFillOnly()`, after `carryWiring()`, so carried wiring is never
overwritten by the default even when the default would correct a renamed storage key.

## Result channels

`orphaned` = children of slots absent from the new type; `droppedWiring` = old wiring keys minus kept;
`droppedProperties` = static property values whose key is absent from the new type (and not a carryable `resolvedBy`
storage key) or whose value its property type rejects. A value rejected for a key the new type still declares as a
primitive with a default is reported as dropped even though that default then re-fills the key; a key absent from
the new type is reported and never re-filled, because the default overlay is keyed only by the new type's primitive
keys. `affected = subtreeIds($replacement)`; `created = [$elementId]` only, because the carried-over children keep
their own nodes while the replaced node is re-scaffolded under the same id.
