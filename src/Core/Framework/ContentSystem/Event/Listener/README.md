# Listener

Event-driven extension points for the content rendering lifecycle. The two events and what each carries: [../README.md](../README.md).

## Guides

- [docs/custom-listeners.md](docs/custom-listeners.md) - The plugin-facing guide to writing a rendering-lifecycle listener.
- [docs/listener-api.md](docs/listener-api.md) - Editing `RenderedElement`, a worked listener, cache tags, and priority.

## Execution Order

The step order is owned by [../../docs/pipeline-steps.md](../../docs/pipeline-steps.md). `ContentPipeline::load()` runs its steps as direct calls ([rule](../../docs/principles/rendering.md#rendering-stages-are-direct-calls-handing-each-other-typed-immutable-results)). What a listener sees at each position: [docs/custom-listeners.md](docs/custom-listeners.md).

Product-domain finalization uses `Product/ContentSystem/ProductVariantSelectionSubscriber` to load options for the product that actually won mapping/fallback delivery. It changes property values only, records selected-product/parent dependencies, and leaves skeletons unchanged. Domain listeners use ordinary event ordering; no priority band is reserved.
