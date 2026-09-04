# Extending the Content System

This is the entry point for the six mechanisms plugins use to extend the ContentSystem, each row linking to where that mechanism is authored.

| Extension Point           | Purpose                                                                |
|---------------------------|------------------------------------------------------------------------|
| **Element Types**         | New content components with declared properties and slots — authored per [Layout/Type/docs/custom-types.md](../Layout/Type/docs/custom-types.md) |
| **Style Options**         | New universal per-breakpoint presentation attributes for every element — authored per [Layout/Element/Style/docs/custom-options.md](../Layout/Element/Style/docs/custom-options.md) |
| **Binding Specifications** | Pre-validated data wirings for an element type, applied in one action — authored per [Binding/docs/custom-specifications.md](../Binding/docs/custom-specifications.md) |
| **Specification Sources** | New URL patterns, entity types — authored per [Adapter/docs/custom-sources.md](../Adapter/docs/custom-sources.md) |
| **Data Loaders**          | External APIs, calculations, aggregated data (with cache control) — authored per [Hydration/DataLoader/docs/custom-loaders.md](../Hydration/DataLoader/docs/custom-loaders.md) |
| **Event Listeners**       | Modify layout structure, enrich data, transform properties, cache tags — authored per [Event/Listener/docs/custom-listeners.md](../Event/Listener/docs/custom-listeners.md) |
