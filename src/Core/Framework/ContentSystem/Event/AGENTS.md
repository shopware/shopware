## Constraints

- Run each rendering stage as a direct call that hands the next a typed immutable record, as `ContentPipeline::load()` does. Never move a stage onto an event or add a core listener. Check: does every stage run as a direct call inside `ContentPipeline::load()`? Why: [rendering.md](../docs/principles/rendering.md#rendering-stages-are-direct-calls-handing-each-other-typed-immutable-results)

## Where to look

- Which event carries which tree and where each dispatches: [README.md](README.md#key-classes)
- Step order around the events and the duplicate-element-id check: [README.md](README.md#lifecycle)
