# DataContext

Context path resolution. `ContextPathResolver` resolves consumer paths on Struct objects; `ContextType` classifies a context entry as `Single` or `Collection`. The distribution of context between elements — `ContextDeliveryResolver`, `ContextDistributor`, `ContextDeliveryIndex` / `ContextDelivery` — lives in [Rendering/](../../Rendering/README.md).

## Key Classes

- `ContextPathResolver` - Resolves dot-notation paths on Struct objects
- `ContextType` - Enum: `Single` / `Collection`, classifying the shape of a context entry on providers, consumers and resolution results

## Path Resolution

`ContextPathResolver` resolves a consumer's dot-notation path (e.g. `product.cover`) against the distributed context, requiring Struct objects at every intermediate step; a `required: true` path that fails throws `ContentSystemException::contextPathNotResolvable()`. The path syntax and the `required` semantics are owned by [Layout/Element/Context/docs/path-resolution.md](../../Layout/Element/Context/docs/path-resolution.md).
