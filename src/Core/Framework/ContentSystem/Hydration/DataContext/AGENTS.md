> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Ordinary context path resolution requires Struct objects at every intermediate step. Catalogued data mappings
  additionally permit one terminal lookup in an entity's array-backed `customFields` member; arbitrary array
  traversal remains unsupported.
