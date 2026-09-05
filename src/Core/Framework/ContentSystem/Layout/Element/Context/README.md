# Context

Context provider and consumer definitions for content elements.

Use context when multiple elements need the same data. Load it once at the parent, share it with all children.

Example: A product page with title, price, and images all showing the same product. Load it once at the page level instead of three separate loads.

## Key Classes

- `ContextProvider` - Defines what context an element exposes to descendants
- `ContextConsumer` - Defines what context an element receives; its `scope` decides the source, an ancestor (`parent`, the default) or the layout's root-ambient context (`root`)
- `ConsumerScope` - The two scopes a consumer takes its value from, `Parent` and `Root`
- `ContextDefinitions` - Container holding providers and consumers for an element
- `ContextDependencyAnalyzer` - Analyzes context dependencies for tree pruning, on `StoredElement`s (the prune runs before the lowering)
- `ProviderDeliveryKeyResolver` - Computes the child-facing key every provider of one element delivers under, and rejects two producers sharing one

## Configuration Reference

- [docs/providers.md](docs/providers.md) - The `providesContext` entry: keys, types, distribution, and `consumerAlias`
- [docs/consumers.md](docs/consumers.md) - The `acceptsContext` entry: keys, types, `required`, `scope`, and `propertyAlias`
- [docs/path-resolution.md](docs/path-resolution.md) - Dot-notation access to nested properties of a provided entity
- [docs/distribution-strategies.md](docs/distribution-strategies.md) - The five distribution strategies and the ancestor-to-descendant flow rules
- [docs/redistribution.md](docs/redistribution.md) - Passing received context through a container with `redistribute: true`
- [docs/worked-example.md](docs/worked-example.md) - One provider feeding three consumer children end to end

## Subdirectories

- **[Distribution/](Distribution/README.md)** - Distribution strategy value objects (Broadcast, Indexed, Keyed, Sliced, Iterator)
