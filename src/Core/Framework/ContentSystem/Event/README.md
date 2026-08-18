# Event

Lifecycle events dispatched around content hydration. The two carry the tree in the model of their own
position: the preparation event carries the stored forest, the post-hydration event the rendered
`list<ContentElement>`. Each exposes exactly one way to put a changed tree back — `replaceTree()` on the
preparation event, whose elements are immutable, and the mutable `elements` property on the other.

## Key Classes

- `ContentTreePreparationEvent` - Dispatched over the stored tree before every preparation step
- `PostHydrationEvent` - Dispatched after hydration; allows layout finalization

## Lifecycle

```
ContentTreePreparationEvent
  → placeholder resolution (FULL mode only) → virtual-root wrap
  → lowering onto ContentElement → redistribute expansion → partial prune
  → Hydration (FULL mode only)
  → virtual-root unwrap → partial extract
→ PostHydrationEvent
```

The steps run inside `ContentPipeline::load()`, not as listeners, so a listener cannot interleave with
them: the preparation event sees the tree before every preparation step, and the post-event sees it after
every finishing step. Core claims no priority band. See [Listener/docs/custom-listeners.md](Listener/docs/custom-listeners.md)
for what a listener may do at each position.

Whether the unwrap runs is decided during preparation, not rediscovered afterwards: `load()` assembles a
`Layout/Scaffolding/RenderScaffolding` and the two finishing steps read it. Only `virtualRootSurvivedPrune`
is read off the post-prune tree — a partial render addressed at an element that needs no page-level context
prunes the virtual root away, so there is nothing left to unwrap. `extractTargetId` is normalised from the
`RenderingSpecification` before the prune, which is the same value the prune runs on.
