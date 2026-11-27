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

`mutate()` first checks the mutation's declared write privilege (below), then runs everything else inside `Layout/LayoutRowLock::run()`: one transaction that locks the layout row before it reads anything.
`LayoutRevisionService` takes the same lock, so a persisted mutation and a publish of one layout serialize, and the later one sees the earlier one's write.
`mutate()` loads by id
(`ContentSystemException::contentLayoutNotFound`, 404, if absent), guards the optimistic-concurrency token against
the row's `updatedAt`. A mismatch is `layoutVersionConflict`, 409, without writing. An unparseable token is a `400`
`invalidVersionToken`. A `null` token matches a never-updated row. `mutate()` applies the op to the loaded `getLayout()` tree
as it is (the entity and the operations speak the same stored model), seeds compatible default mappings only on `freshlyScaffolded()` IDs, and persists the mutated tree's `roots` via
`update()`, which runs the resolvability gates and rejects a resolvability-breaking edit.
`mutate()` diagnoses the mutated tree inside the same transaction, so a failing diagnosis rolls the write back, and returns the `MutationResult`.

A mutation may declare a write privilege through `LayoutMutation::writePrivilege()`. Only `Op/TranslateElement` does
(`content_layout:translate`). `mutate()` throws `ContentSystemException::missingPrivileges` (`403`) when
`Context::isAllowed()` denies it, before the lock, the load, the version guard and `apply()`, so a caller lacking it
gets the `403` ahead of any read, `404`, `409` or `400`. The private `commit()` then runs the `update()` call alone in
`Context::SYSTEM_SCOPE` through `Context::scope()`: the load before it and the `diagnose()` after it stay on the caller's context, because
`Context::scope()` switches the very context it is called on. In system scope `AclWriteValidator` checks no privilege,
while `Validation/ContentLayoutWriteValidator`, the lock and the version guard run as for every write. A `null`
privilege writes on the caller's context unchanged. `MutationPipeline` persists nothing and never reads the privilege.

The token is compared at storage precision: `content_layout.updated_at` is `DATETIME(3)`, and the Admin API
serializes `updatedAt` at millisecond precision, so seconds plus milliseconds are compared rather than
microseconds.

`mutate()` runs no consumer mirroring at all, so a live persisted mutation commits without mirrored wiring.

The locked layout's `root_source` initializes `StoredTree::$rootSource`. Operations, default seeding, and tree transforms use and preserve that source. Response diagnostics resolve it through `RootSourceRegistry::resolveGated()`; binding checks run for bound sources, including an empty context for `none`/header/footer. The preceding update checks source membership and rejects a de-registered source before commit. Internal `MapProperty` calls without a source fail with `MAPPING_ROOT_SOURCE_MISSING` (500); missing/blank or unknown request sources remain HTTP 400.

Content the op detaches (`orphaned`), wiring it drops (`droppedWiring`), and static property values it cannot carry
(`droppedProperties`) come back in the `MutationResult`. The caller can then re-place orphans with `Op/AttachElement`, re-wire dropped keys, or re-apply dropped
values.

### Draft target

`mutateDraft()` applies the mutation to the head of a draft, a named pointer into the revision graph, instead of the live tree.
It checks the declared write privilege first, exactly like `mutate()`.
`expectedHeadRevisionId` is the draft head revision id the caller last saw; a stale head is `layoutVersionConflict`, 409.
`Layout/Revision/LayoutRevisionService::appendRevision()` checks the head under the layout row lock and hands the head tree to the mutation.
The mutation runs through `MutationPipeline::run()`, consumer mirroring included, against the layout's `root_source` and its resolved root context.
The result is admitted for well-formedness only and appended as one revision whose parent is the old head.
The live `layout` column is not written; publishing is what enforces resolvability.
A rejected operation or admission appends nothing and leaves the head where it was.
`mutateDraft()` returns a `DraftMutationResult` with the pipeline's `MutationResult`, the new revision and the moved draft.

### Interim limitations

Drafts guard their own edits with `expectedHeadRevisionId` and leave `expectedVersion` in place for live mutations, so one limitation remains:

1. A layout write through the DAL takes neither the lock nor the `expectedVersion` token, so it overwrites a
   persisted mutation that committed after the writer loaded the layout, and neither side gets an error.
   Drafts only detect such a write: the next draft write adopts the column as the live revision, and drafts opened
   before it can no longer be published, see `LayoutRevisionService::reconcile()`.

## MutationResult assembly

`MutationResult` is `@internal final readonly` with a private constructor, reached through named constructors:

- `fromAnalyzedMutation()` is the single owner of the result assembly. It restricts the analysis resolutions to the
  mutation's `affected()` set and reads `orphaned`/`droppedWiring`/`droppedProperties` off the mutation. Both
  `MutationPipeline::run()` and `mutate()` call it, so the affected-set restriction is stated once.
- `fromParts()` is the direct passthrough for a bespoke result.
