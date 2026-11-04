# Event

Lifecycle events dispatched around content rendering. The preparation event carries the stored forest, the
finalization event the rendered one. Each event exposes exactly one way to put a changed tree back, `replaceTree()`.
Neither exposes `RenderingMode`.

Both `replaceTree()` and both constructors refuse a replacement that is not a list of that event's own
element model. The two models are the mistake the storage/render split invites, and the pipeline's own
post-event check does not catch it: that check reads `id`, which both models carry. Without the guard a
rendered element handed to the preparation event reaches the preparation steps and fails there: as a
`TypeError` on a closure parameter in FULL mode, and in SKELETON mode not until `WiringPlanner` reads
`contextDefinitions` off an element that declares none. What gets reported then names a core internal rather
than the listener that caused it. Depth needs no walk at the event: a `StoredElement` refuses a rendered slot
child and a `RenderedElement` refuses a stored one, so a list of valid roots is a valid forest.

## Key Classes

- `ContentTreePreparationEvent` - Dispatched over the stored tree before every preparation step
- `RenderedTreeFinalizationEvent` - Dispatched after the render step and the [finishing steps](../docs/principles/README.md#glossary), over the rendered forest and before the duplicate-element-id check

## Lifecycle

The step order around the two events is owned by [../docs/pipeline-steps.md](../docs/pipeline-steps.md).

The duplicate-element-id check runs twice, on the stored forest before the [partial prune](../docs/principles/README.md#glossary) and on the finished rendered forest, in either rendering mode. See [The render validates the whole stored forest in every mode](../docs/principles/rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode).

The steps run inside `ContentPipeline::load()` as direct calls ([rule](../docs/principles/rendering.md#rendering-stages-are-direct-calls-handing-each-other-typed-immutable-results)). What a listener sees and may do at each position: [Listener/docs/custom-listeners.md](Listener/docs/custom-listeners.md).
