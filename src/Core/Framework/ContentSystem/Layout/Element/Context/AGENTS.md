> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- `redistribute: true` generates virtual provider at runtime (priority 4000) — never persisted
- `consumerAlias` requires `redistribute: true` — validated in `ContextConsumersFieldSerializer`
- `propertyAlias` does NOT require `redistribute` (independent)
- Property alias: no dots, unique per element — validated in serializer and `RedistributeExpansionSubscriber`
