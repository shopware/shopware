# Runners

The two things that run an operation: `MutationPipeline`, the stateless runner over a decoded draft tree, and
`PersistedLayoutMutator`, which commits one operation to a stored `content_layout`. Both assemble their outcome
through the same `MutationResult` named constructor.

## MutationPipeline

`@internal`, with a `@final` annotation (behaviorally final, kept mockable for tests). Constructor:
`LayoutDiagnostics $diagnostics, PageContextConsumerWiring $contextWiring`.

`run(LayoutMutation $mutation, StoredTree $tree, ?array $rootContext): MutationResult` applies the mutation to the
already-decoded `$tree`, diagnoses the whole new tree, mirrors the proven consumers onto `$mutation->created()` via
`PageContextConsumerWiring::apply()`, re-diagnoses when (and only when) the wiring returned a different `StoredTree`
instance, and hands the wired tree, the surviving analysis and the mutation to
`MutationResult::fromAnalyzedMutation()`, which owns the restriction of the returned resolutions to the affected set
(`array_intersect_key($analysis->resolutions, array_flip($affected))`).

The instance-identity check is the whole gate: mirroring returns the input tree unchanged when it writes no
consumer, so the common case stays at one analysis pass while a wired response never carries diagnostics describing
a pre-wiring tree.

`$rootContext` is the bound source's root-ambient context (`list<ProvidedContext>`) or `null` for the
well-formedness-only subset. Decoding the request draft is the caller's job (`Api/DraftLayoutDecoder`, the
structural pre-gate shared with the preview and diagnose routes); the pipeline is agnostic to whether the tree came
from a request draft or a loaded `content_layout`. Stateless: it never persists.

## PersistedLayoutMutator

`@internal`, `@final` annotation. The persisted counterpart to `MutationPipeline`. Constructor:
`LockFactory $lockFactory, EntityRepository<ContentLayoutCollection> $contentLayoutRepository, RootSourceRegistry $rootSourceRegistry, LayoutDiagnostics $diagnostics`.

`mutate(string $layoutId, ?string $expectedVersion, LayoutMutation $mutation, Context $context): MutationResult`
serializes concurrent writers for the layout id under a `lock.factory` named lock so the load → version-check →
commit span is atomic, closing the lost-update window. It then loads by id
(`ContentSystemException::contentLayoutNotFound`, 404, if absent), guards the optimistic-concurrency token against
the row's `updatedAt` (`layoutVersionConflict`, 409, without writing on a mismatch; an unparseable token is a `400`
`invalidVersionToken`; a `null` token matches a never-updated row), applies the op to the loaded `getLayout()` tree
as it is (the entity and the operations speak the same stored model), then persists the mutated tree's `roots` via
`update()`, which runs the resolvability gates and rejects a resolvability-breaking edit, and returns the
`MutationResult`.

The token is compared at storage precision: `content_layout.updated_at` is `DATETIME(3)`, and the Admin API
serializes `updatedAt` at millisecond precision, so seconds plus milliseconds are compared rather than
microseconds.

It runs no consumer mirroring at all, so a persisted mutation commits without mirrored wiring.

The response diagnostics are derived from the loaded layout's single `root_source`, resolved once via
`RootSourceRegistry::resolve()`, which returns a list and never `null` (`[]` for `none` / header / footer), so the
binding-scope checks always run. `resolve()` is never handed an unregistered id here: the preceding `update()` runs
`Validation/ContentLayoutWriteValidator`, which re-checks membership of the committed root source and rejects a
de-registered source as a clean `unknownRootSource` 400 before any commit.

Content the op detaches (`orphaned`), wiring it drops (`droppedWiring`), and static property values it cannot carry
(`droppedProperties`) are never lost: the committed tree omits them and they ride back in the `MutationResult` so
the caller can re-place them with `Op/AttachElement` (orphans), re-wire (dropped keys), or re-apply (dropped
values).

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

`MutationResult` is `@internal final readonly` with a private constructor, reached through two named constructors:

- `fromAnalyzedMutation(StoredTree $mutated, LayoutAnalysis $analysis, LayoutMutation $mutation): self` is the
  single owner of the result assembly. It restricts the analysis resolutions to the mutation's `affected()` set via
  `array_intersect_key($analysis->resolutions, array_flip($affected))` and reads
  `orphaned`/`droppedWiring`/`droppedProperties` off the mutation. Both `MutationPipeline::run()` and `mutate()` call
  it, so the affected-set restriction is stated once.
- `fromParts(StoredTree $layout, array $resolutions, DiagnosticsReport $diagnostics, array $affectedElementIds, array $orphaned = [], array $droppedWiring = [], array $droppedProperties = []): self`
  is the direct passthrough for a bespoke result.

`$resolutions` is `array<string, list<PropertyResolution>>` keyed by element id; `$droppedProperties` is
`array<string, StoredValue>` keyed by property key.
