# Event

Lifecycle events dispatched during content hydration. Both events share identical structure — `elements` (mutable `list<ContentElement>`) is the only writable property.

## Key Classes

- `PreContentHydrationEvent` - Dispatched before hydration; allows layout preparation
- `PostHydrationEvent` - Dispatched after hydration; allows layout finalization

## Lifecycle

```
PreContentHydrationEvent
  → virtual-root wrap → placeholder resolution → redistribute expansion → partial prune
  → Hydration (FULL mode only)
  → virtual-root unwrap → partial extract
→ PostHydrationEvent
```

The six steps are private methods on `ContentPipeline`, not listeners, so a listener cannot interleave with
them: the pre-event sees the tree before every preparation step, and the post-event sees it after every
finishing step. Core claims no priority band. See [Listener/docs/custom-listeners.md](Listener/docs/custom-listeners.md)
for what a listener may do at each position.
