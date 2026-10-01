> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Navigation

- Why these constraints hold, and what was not chosen: [rendering.md](../docs/principles/rendering.md)

## Constraints

- Run each rendering stage as a direct call that hands the next a typed immutable record, as `ContentPipeline::load()` does. Never move a stage onto an event or add a core listener: the two tree-replacement events are the only extension sites. Check: `ContentPipelineTest` pins the two dispatches.
