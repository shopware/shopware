> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Multi-root layouts: element-provided context is tree-scoped, not layout-scoped (a provider in one root reaches nothing in another). Root-ambient context is the exception and is layout-scoped by construction: it is an explicit render input rather than anything a tree provides, so every `ConsumerScope::Root` consumer in every root receives it
- `Scaffolding/VirtualRootWrapper` carries the page-level placeholder values and the wrap/unwrap prune scaffolding, and nothing else: no providers, no consumers, no data requirements. It distributes nothing to the roots in its slot
- Element IDs unique across all roots (partial rendering searches all)
- Placeholders (`{{key}}`) resolved in single pass on the stored tree, in FULL mode only (`Scaffolding/StoredTreePreparer`) — no recursive resolution, and no descent into a list or map property value
- Field/ serializers are infrastructure — only interact in EntityDefinition classes
