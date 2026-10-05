# Consumer Mirroring

`ContextConsumerMirror` is the creation-time step `MutationPipeline` runs after the diagnostics pass. It mirrors
onto the created elements the `acceptsContext` consumers their own resolutions already prove, and nothing else. It
is `@internal` and absent from `InternalClassRule`'s
public-surface allowlist.

## What proves a consumer

A resolution yields a consumer only when its `kind` is `Reference` and its `resolved` candidate is non-null, with a
non-empty, non-integer-like `contextKey` and non-null `contextType`. The candidate's origin must be `Parent` or
`Root` (`scope: ConsumerScope::Root`). `Loader` and `Stored` origins fill themselves and are skipped, as is a `null`
`resolved`. An integer-like `contextKey` (e.g. `"0"`, `"42"`) is skipped too: PHP coerces such a key to an int on
the consumer-map write, and `StoredElementWiringDecoder::decodeConsumers()` rejects a non-string consumer key at
decode time.

The consumer is keyed by the resolved `contextKey`. It carries `propertyAlias: $resolution->key` whenever the
resolution's own key differs from that `contextKey`, and `null` when they are equal.

A cross-key resolution whose written property key (`$resolution->key`) contains a dot is skipped.
`StoredElementWiringDecoder` rejects a dotted `propertyAlias` at decode time, so mirroring one would write a tree its
next decode throws on. An equal dotted key is unaffected, because a dotted consumer key with no `propertyAlias` is legal.

No redistribute, no relay up the ancestor chain. Every matching resolution on an element yields its own consumer, so
an element consuming two keys gets two.

## The skips

These skips apply per resolution:

1. A consumer the element already carries under the same resolved `contextKey`, never overwritten.
2. A base-key collision against any existing consumer, comparing the base keys (the first dotted segment) of two
   written property keys: the resolution's own (`$resolution->key`) and that consumer's own (`propertyAlias ?? consumerKey`).
   This is the same axis the decode-time element-wiring validation
   enforces, so mirroring never writes a pair the next decode would reject with `propertyAliasCollision`.
3. The written property key present in the element's own `dataRequirements`, because a loader fills it.
4. The written property key present in the element's own provider key set, because auto-mirroring would silently
   turn a provider into a conduit for same-named upstream context.

## Rebuild

Mirroring rebuilds each element and its ancestors through its own recursion over the slots, because
`StoredTree::locate()` carries no ancestors.

A `ReplaceElement` target counts as `created()` ([replace-element.md](replace-element.md)).

See [The draft pipeline writes derived wiring only where proved](../../docs/principles/mutation.md#the-draft-pipeline-writes-derived-wiring-only-where-proved).
