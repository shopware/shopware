# Event

Lifecycle events dispatched around content rendering. The two carry the tree in the model of their own
position: the preparation event carries the stored forest, the finalization event the rendered one. Each event exposes exactly one way to put a changed tree back, and it is the
same way: `replaceTree()`. Neither exposes `RenderingMode`.

## Key Classes

- `ContentTreePreparationEvent` - Dispatched over the stored tree before every preparation step
- `RenderedTreeFinalizationEvent` - Dispatched after the render step and the finishing steps, over the rendered forest and before the duplicate-element-id check that judges what it hands back

## Lifecycle

The step order around the two events is owned by [../docs/pipeline-steps.md](../docs/pipeline-steps.md).

The duplicate-element-id check runs twice, on the pre-prune stored forest and on the finished rendered forest, in either rendering mode. See [The render validates the whole stored forest in every mode](../docs/principles/rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode).

The steps run inside `ContentPipeline::load()` as direct calls ([rule](../docs/principles/rendering.md#rendering-stages-are-direct-calls-handing-each-other-typed-immutable-results)). What a listener sees and may do at each position: [Listener/docs/custom-listeners.md](Listener/docs/custom-listeners.md).
