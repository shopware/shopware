# Listener

Event-driven extension points for the content rendering lifecycle. A listener replaces the stored forest via `ContentTreePreparationEvent::replaceTree()` before data loading, and replaces the rendered forest via `RenderedTreeFinalizationEvent::replaceTree()` after it. Neither event exposes its forest for mutation: both hold it privately behind `tree()`.

## Guides

- [docs/custom-listeners.md](docs/custom-listeners.md) - The plugin-facing guide to writing a rendering-lifecycle listener.
- [docs/listener-api.md](docs/listener-api.md) - Editing `RenderedElement`, a worked listener, cache tags, and priority.

## Execution Order

The step order is owned by [../../docs/pipeline-steps.md](../../docs/pipeline-steps.md). `ContentPipeline::load()` (module root) runs its preparation and finishing steps as direct calls, not through the two events. A `ContentTreePreparationEvent` listener therefore always sees the raw loaded forest, and a `RenderedTreeFinalizationEvent` listener always sees the finished rendered forest, at any priority.

## Priorities

Priority only orders extension listeners against each other on the same event, and core reserves no band. See [docs/listener-api.md](docs/listener-api.md#priorities).
