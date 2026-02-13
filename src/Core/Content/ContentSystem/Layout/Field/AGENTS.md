@README.md

## Constraints

- Validation: Symfony `Collection` constraint with `allowMissingFields: false` + `Optional` per field
- Composition root: `ContentElementFieldSerializer` (injects all child serializers)
- Recursive: `ElementSlotsFieldSerializer` injects `ContentElementFieldSerializer` for tree serialization
- Infrastructure only — used in `ContentLayoutDefinition`, not domain API
