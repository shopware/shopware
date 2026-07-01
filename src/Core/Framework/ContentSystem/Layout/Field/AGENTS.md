@README.md

## Constraints

- Validation: Symfony `Collection` constraint with `allowMissingFields: false` + `Optional` per field
- Composition root: `ContentElementFieldSerializer` (injects all child serializers)
- Recursive: `ElementSlotsFieldSerializer` injects `ContentElementFieldSerializer` for tree serialization; `ElementSlotsFieldSerializer` is registered `lazy="true"` to break the `ContentElementFieldSerializer` ↔ `ElementSlotsFieldSerializer` circular dependency
- `ContentElementListFieldSerializer` overrides `normalize` to run two write-boundary passes over the layout payload, ahead of `PreWriteValidationEvent`: it first seeds the element types' primitive defaults via the injected `Layout/LayoutDefaultSeeder` (the `IdFieldSerializer::normalize` precedent, never overwriting an authored value), then re-derives each element's `attributedSpecifications` via `Binding/AttributionReconciler` (drop-not-throw: an attribution whose wiring has since diverged is dropped, never an error). Both handle the `ContentElement` and raw-array payload shapes
- Infrastructure only — used in `ContentLayoutDefinition`, not domain API
