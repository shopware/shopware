# Hydration

The data-loading half of the render step: the loaders that fetch an element's required data live in DataLoader/, and the context-path utilities that remain live in DataContext/. The render step itself — data resolution, context delivery, and the minting of the rendered tree — lives in [Rendering/](../Rendering/README.md).

## Subdirectories

- **[DataLoader/](DataLoader/README.md)** - Data fetching (`AbstractContentDataLoader` implementations)
- **[DataContext/](DataContext/README.md)** - Context path resolution (`ContextPathResolver`, `ContextType`)
