# Consumer Mirroring

`ContextConsumerMirror` is the creation-time step `MutationPipeline` runs after the diagnostics pass. It mirrors
onto the created elements the `acceptsContext` consumers their own resolutions already prove, and nothing else. It
is `@internal`, `#[Package('framework')]` like everything else here, and absent from `InternalClassRule`'s
public-surface allowlist. Only the draft pipeline calls it, so a persisted mutation commits without mirrored wiring.

`apply(StoredTree $tree, array $resolutions, array $createdElementIds): StoredTree`.

## What proves a consumer

A resolution yields a consumer only when its `kind` is `Reference`, its `resolved` candidate is non-null with a
non-empty `contextKey` and a non-null `contextType`, and that candidate's origin is `Parent` or `Root`
(`scope: ConsumerScope::Root`). `Loader` and `Stored` origins fill themselves and are skipped, as is a `null`
`resolved`.

The consumer is keyed by the resolved `contextKey`. It carries `propertyAlias: $resolution->key` whenever the
resolution's own key differs from that `contextKey`, and `null` when they are equal, because the property key the
consumer writes must be the reference property whose resolution proved the consumer, or delivery fills a foreign key
while the declared property stays empty.

A cross-key resolution whose written property key (`$resolution->key`) contains a dot is skipped:
`StoredElementWiringDecoder` rejects a dotted `propertyAlias` at decode time, so mirroring one would write a tree its
next decode throws on. An equal
dotted key is unaffected, because a dotted consumer key with no `propertyAlias` is legal.

No redistribute, no relay up the ancestor chain. Every matching resolution on an element yields its own consumer, so
an element consuming two keys gets two.

## The four skips

Four skips apply per resolution:

1. A consumer the element already carries under the same resolved `contextKey`, never overwritten.
2. A base-key collision against any existing consumer, comparing the base key (the first dotted segment) of the
   resolution's own written property key (`$resolution->key`) against the base key of that consumer's own written
   property key (`propertyAlias ?? consumerKey`). This is the same axis the decode-time element-wiring validation
   enforces, so mirroring never writes a pair the next decode would reject with `propertyAliasCollision`.
3. The written property key present in the element's own `dataRequirements`, because a loader fills it.
4. The written property key present in the element's own provider key set, because auto-mirroring would silently
   turn a provider into a conduit for same-named upstream context.

The last two test the written property key rather than the `contextKey`, deliberately: with equal keys, the common
case, behavior is unchanged, but a cross-key mirror is blocked only when the written key itself collides with a data
requirement or a provider, never merely because its resolved `contextKey` happens to match one that fills a
different property.

## Rebuild and the identity contract

Element rebuild goes through
`StoredElement::withContextDefinitions(new ContextDefinitions($definitions->getAllProviders(), $consumers))` and a
parent-rebuilding recursion over `StoredElement::$slots`, because `StoredTree::locate()` carries no ancestors.

Writing no consumer returns the input `StoredTree` instance itself, which is what the pipeline's re-analysis gate
reads.

Restricting mirroring to `created()` is what makes an explicit unwiring durable: the ambient offer that proved the
reference does not go away, so mirroring on a non-creating mutation would write back in the same response the
consumer that was just removed.
