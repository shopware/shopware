# Event

Lifecycle events dispatched around content rendering. The two carry the tree in the model of their own
position: the preparation event carries the stored forest, the finalization event the rendered one. Both
elements models are immutable, so each event exposes exactly one way to put a changed tree back, and it is
the same way: `replaceTree()`. Neither exposes `RenderingMode`.

## Key Classes

- `ContentTreePreparationEvent` - Dispatched over the stored tree before every preparation step
- `RenderedTreeFinalizationEvent` - Dispatched after the render step and the finishing steps, over the rendered forest and before the duplicate-element-id check that judges what it hands back; allows layout finalization

## Lifecycle

```
ContentTreePreparationEvent
  → StoredTreePreparer: placeholder resolution (FULL mode only)
      → virtual-root wrap → partial prune
  → duplicate-element-id check + wiring validation (on the pre-prune forest)
  → redistribute derivation (on the pruned tree)
  → render step: ElementLowering (FULL resolves data and context;
      SKELETON mints structure only)
  → virtual-root unwrap → partial extract (both on the rendered forest)
→ RenderedTreeFinalizationEvent
  → duplicate-element-id check (on the forest the event handed back)
```

The duplicate-element-id check runs twice on purpose. The first pass judges the pre-prune stored forest, so a
twin the prune or the later target extract would have removed still fails the render instead of leaving the
response serving one of two ambiguous elements. The second judges the finished rendered forest, because a
finalization listener may replace the tree and a duplicate it introduces is invisible to the first pass. Both
throw `CONTENT_SYSTEM__DUPLICATE_ELEMENT_ID` (500), and both run in either rendering mode.

The steps run inside `ContentPipeline::load()`, not as listeners, so a listener cannot interleave with
them: the preparation event sees the tree before every preparation step, and the finalization event sees it
after every finishing step. Core claims no priority band. See [Listener/docs/custom-listeners.md](Listener/docs/custom-listeners.md)
for what a listener may do at each position.

Whether the unwrap runs is decided during preparation, not rediscovered afterwards: `StoredTreePreparer`
returns a `Layout/Scaffolding/TreePreparationResult` carrying a `Layout/Scaffolding/RenderScaffolding`,
and the two finishing steps read it. Only `virtualRootSurvivedPrune`
is read off the post-prune tree — a partial render addressed at an element that needs no page-level context
prunes the virtual root away, so there is nothing left to unwrap. `extractTargetId` is normalised from the
`RenderingSpecification` before the prune, which is the same value the prune runs on.
