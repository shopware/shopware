# DataContext

Context resolution and distribution. Providers expose data as context, consumers receive it in properties. Intermediate elements don't need to know about context passing through them.

## Key Classes

- `ContextDeliveryResolver` - Entry point, walks the stored forest top-down and returns a `ContextDeliveryIndex`
- `ContextDistributor` - The rule for ONE parent and its direct children: computes what each child receives and returns it
- `ContextDeliveryIndex` / `ContextDelivery` - What every element of that forest received, by element id; computed once, read back afterwards
- `ContextPathResolver` - Resolves dot-notation paths on Struct objects

`DataContextResolver` and `ContextResolutionVisitor` are the pre-split implementation, which wrote context into mutable `ContentElement`s. Neither is on the serving path any more.

## Distribution

`ContextDistributor` distributes context ONLY to direct children — never recursive. Multi-level requires explicit re-providing (`acceptsContext` + `providesContext`). The forest walk is separate and belongs to `ContextDeliveryResolver`; it runs top-down, so a container that receives context and re-provides it distributes what it was given.

The five strategies `ContextDistributor` dispatches on are declared in `Layout/Element/Context/Distribution/`: Broadcast, Indexed, Keyed, Sliced, Iterator. What each one means, and the context-flow rules bounding how far a distributed context reaches, are owned by [Layout/Element/Context/docs/distribution-strategies.md](../../Layout/Element/Context/docs/distribution-strategies.md).

## Path Resolution

`ContextPathResolver` resolves a consumer's dot-notation path (e.g. `product.cover`) against the distributed context, requiring Struct objects at every intermediate step; a `required: true` path that fails throws `ContentSystemException::contextPathNotResolvable()`. The path syntax and the `required` semantics are owned by [Layout/Element/Context/docs/path-resolution.md](../../Layout/Element/Context/docs/path-resolution.md).
