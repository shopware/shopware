# Field

Custom DAL field types for persisting ContentElement structures to JSON. Each field extends `JsonField` with a corresponding serializer for encode/decode. Each serializer is registered with the `shopware.field_serializer` DAL tag (seven in total) so the framework's serializer registry discovers it.

## Key Classes

- `ContentElementField` / `ContentElementFieldSerializer` - Single ContentElement
- `ContentElementListField` / `ContentElementListFieldSerializer` - ContentElement arrays (the `content_layout.layout` column). Its `normalize` hook runs two write-boundary passes ahead of the resolvability gate: `Layout/LayoutDefaultSeeder` seeds the element types' primitive defaults, then `Binding/AttributionReconciler` re-derives each element's `attributedSpecifications` against its current wiring (dropping a diverged attribution, never throwing)
- `ElementSlotsField` / `ElementSlotsFieldSerializer` - Slot arrays
- `ElementStyleField` / `ElementStyleFieldSerializer` - The element's universal `ElementStyle`. The write path validates it against the style option registry; the read path is registry-free and keeps unknown options verbatim (the strict write rejects them). Composed into `ContentElementFieldSerializer`
- `DataRequirementsField`, `ContextProvidersField`, `ContextConsumersField` - With matching serializers

Serializers compose each other for nested structures — `ContentElementFieldSerializer` delegates to child serializers for slots, requirements, and context definitions, enabling recursive tree serialization.
