# Event

Lifecycle events dispatched around content rendering. The preparation event carries the stored forest, the
finalization event the rendered one. Each event exposes exactly one way to put a changed tree back, `replaceTree()`.
Neither exposes `RenderingMode`.

## Key Classes

- `ContentTreePreparationEvent` - Dispatched over the stored tree before every preparation step
- `RenderedTreeFinalizationEvent` - Dispatched after the render step and the [finishing steps](../docs/principles/README.md#glossary), over the rendered forest and before the duplicate-element-id check

## Lifecycle

The step order around the two events is owned by [../docs/pipeline-steps.md](../docs/pipeline-steps.md).

The duplicate-element-id check runs twice, on the stored forest before the [partial prune](../docs/principles/README.md#glossary) and on the finished rendered forest, in either rendering mode. See [The render validates the whole stored forest in every mode](../docs/principles/rendering.md#the-render-validates-the-whole-stored-forest-in-every-mode).

The steps run inside `ContentPipeline::load()` as direct calls ([rule](../docs/principles/rendering.md#rendering-stages-are-direct-calls-handing-each-other-typed-immutable-results)). What a listener sees and may do at each position: [Listener/docs/custom-listeners.md](Listener/docs/custom-listeners.md).
