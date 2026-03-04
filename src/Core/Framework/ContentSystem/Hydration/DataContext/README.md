# DataContext

Context resolution and distribution. Providers expose data as context, consumers receive it in properties. Intermediate elements don't need to know about context passing through them.

## Key Classes

- `DataContextResolver` - Entry point, calls `element.traverse()` with `ContextResolutionVisitor`
- `ContextResolutionVisitor` - Traverses tree, distributes context to children
- `ContextPathResolver` - Resolves dot-notation paths on Struct objects

## Distribution

Context distributes ONLY to direct children — never recursive. Multi-level requires explicit re-providing (`accepts_context` + `provides_context`).

Five strategies (in `Layout/Element/Context/Distribution/`):
- **Broadcast** — Same data to all consumers
- **Indexed** — Position-based (consumer N gets data[N])
- **Keyed** — Consumer's `data_key` matches keys in provider data
- **Sliced** — Collection chunked across consumers
- **Iterator** — Round-robin distribution

## Path Resolution

Consumers request nested properties via dot notation (e.g., `product.cover`). Requires Struct objects at every intermediate step. `required: true` throws `ContentSystemException::contextPathNotResolvable()`.
