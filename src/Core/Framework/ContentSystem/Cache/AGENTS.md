## Constraints

- Invalidate a tag the module defines on every change of the value it covers, and invalidate the tags of both the previous and the new target when a write moves a reference. Check: does every write that changes a tagged value invalidate its tag, and does an assignment move read the previous target before the write? Why: [caching.md](../docs/principles/caching.md#the-http-cache-stores-a-render-only-when-a-recorded-tag-covers-every-value-it-shows), [caching.md](../docs/principles/caching.md#a-write-that-moves-a-reference-invalidates-the-tags-of-both-targets)

## Where to look

- Rendering cache context lifecycle, supported entities and their cache tag patterns: [README.md](README.md#key-classes), [README.md](README.md#cache-tag-patterns)
- Entity writes that invalidate cached pages: [README.md](README.md#invalidation)
- Mapping-specific media dependencies and custom-field metadata invalidation, scoped to layouts attempting those mappings: [README.md](README.md#cache-tag-patterns), [README.md](README.md#invalidation)
