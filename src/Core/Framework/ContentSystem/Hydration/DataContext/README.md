# DataContext

Context resolution and distribution. Providers expose data as context, consumers receive it in properties. Intermediate elements don't need to know about context passing through them.

## Key Classes

- `DataContextResolver` - Entry point, calls `element.traverse()` with `ContextResolutionVisitor`
- `ContextResolutionVisitor` - Traverses tree, distributes context to children
- `ContextPathResolver` - Resolves dot-notation paths on Struct objects

## Distribution

`ContextResolutionVisitor` distributes context ONLY to direct children — never recursive. Multi-level requires explicit re-providing (`acceptsContext` + `providesContext`).

The five strategies it dispatches on are declared in `Layout/Element/Context/Distribution/`: Broadcast, Indexed, Keyed, Sliced, Iterator. What each one means, and the context-flow rules bounding how far a distributed context reaches, are owned by [Layout/Element/Context/docs/distribution-strategies.md](../../Layout/Element/Context/docs/distribution-strategies.md).

## Path Resolution

`ContextPathResolver` resolves a consumer's dot-notation path (e.g. `product.cover`) against the distributed context, requiring Struct objects at every intermediate step; a `required: true` path that fails throws `ContentSystemException::contextPathNotResolvable()`. The path syntax and the `required` semantics are owned by [Layout/Element/Context/docs/path-resolution.md](../../Layout/Element/Context/docs/path-resolution.md).
