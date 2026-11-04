# Runners

The things that run an operation: `MutationPipeline`, the stateless runner over a decoded draft tree, and
`PersistedLayoutMutator`, which commits one operation to a stored `content_layout`.

## MutationPipeline

`@internal`, with a `@final` annotation (behaviorally final, kept mockable for tests).

`run(LayoutMutation $mutation, StoredTree $tree, ?array $rootContext): MutationResult` operates on an already-decoded tree:

1. Apply the mutation.
2. Seed compatible default mappings on its explicit `freshlyScaffolded()` IDs.
3. Diagnose the whole new tree, passing the source from `StoredTree::$rootSource`.
4. Mirror proven consumers onto the same fresh scaffolds.
5. Re-diagnose only if mirroring returned a different `StoredTree` instance.
6. Assemble the result from the wired tree, surviving analysis, and mutation reports.

The draft controller initializes the tree's source from the request; null remains valid for unbound drafts. Mapping actions validate or load a bound source first. Copies and ordinary reattachments report created IDs without fresh scaffolds; presets explicitly opt in. Preserve the source with `StoredTree::withRoots()` rather than duplicating it in operation constructors or runner/seeder arguments.

The instance-identity check alone gates the rerun. Mirroring returns the input tree unchanged when it writes
no consumer, so the common case stays at one analysis pass. A response never carries diagnostics of the tree before
mirroring.

`$rootContext` is the bound source's root context (`list<ProvidedContext>`). `null` skips ordinary binding checks; explicit inline tokens can still report a missing bound source. See [mapping checks](../../Diagnostics/README.md#mapping-checks).

## PersistedLayoutMutator

`@internal`, `@final` annotation. The persisted counterpart to `MutationPipeline`.

`mutate()` serializes concurrent writers for the layout id under a `lock.factory` named lock so the load → version-check →
commit span is atomic, closing the lost-update window. `mutate()` loads by id
(`ContentSystemException::contentLayoutNotFound`, 404, if absent), guards the optimistic-concurrency token against
the row's `updatedAt`. A mismatch is `layoutVersionConflict`, 409, without writing. An unparseable token is a `400`
`invalidVersionToken`. A `null` token matches a never-updated row. `mutate()` applies the op to the loaded `getLayout()` tree
as it is (the entity and the operations speak the same stored model), seeds compatible default mappings only on `freshlyScaffolded()` IDs, and persists the mutated tree's `roots` via
`update()`, which runs the resolvability gates and rejects a resolvability-breaking edit. `mutate()` returns the
`MutationResult`.

The token is compared at storage precision: `content_layout.updated_at` is `DATETIME(3)`, and the Admin API
serializes `updatedAt` at millisecond precision, so seconds plus milliseconds are compared rather than
microseconds.

It runs no consumer mirroring at all, so a persisted mutation commits without mirrored wiring.

The locked layout's `root_source` initializes `StoredTree::$rootSource`. Operations, default seeding, and tree transforms use and preserve that source. Response diagnostics resolve it through `RootSourceRegistry::resolveGated()`; binding checks run for bound sources, including an empty context for `none`/header/footer. The preceding update checks source membership and rejects a de-registered source before commit. Internal `MapProperty` calls without a source fail with `MAPPING_ROOT_SOURCE_MISSING` (500); missing/blank or unknown request sources remain HTTP 400.

Content the op detaches (`orphaned`), wiring it drops (`droppedWiring`), and static property values it cannot carry
(`droppedProperties`) come back in the `MutationResult`. The caller can then re-place orphans with `Op/AttachElement`, re-wire dropped keys, or re-apply dropped
values.

### Interim limitations

Two known limitations are deferred to the planned layout draft/versioning system that will supersede this
`expectedVersion`/lock mechanism:

1. The per-layout lock has a fixed `5.0`s TTL, so a critical section that runs longer could let the lock expire
   mid-write and reopen the lost-update window the lock closes. The optimistic `updatedAt` token still narrows that
   window but does not eliminate it.
2. `diagnose()` runs after `update()` has already committed, so if it throws the caller sees an error even though
   the write landed. The committed tree is the source of truth, and a retry re-reads the bumped `updatedAt` and gets
   a `409` `layoutVersionConflict`.

## MutationResult assembly

`MutationResult` is `@internal final readonly` with a private constructor, reached through named constructors:

- `fromAnalyzedMutation()` is the single owner of the result assembly. It restricts the analysis resolutions to the
  mutation's `affected()` set and reads `orphaned`/`droppedWiring`/`droppedProperties` off the mutation. Both
  `MutationPipeline::run()` and `mutate()` call it, so the affected-set restriction is stated once.
- `fromParts()` is the direct passthrough for a bespoke result.
