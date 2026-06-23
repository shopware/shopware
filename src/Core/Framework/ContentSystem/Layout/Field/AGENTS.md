@README.md

## Constraints

- Validation: Symfony `Collection` constraint with `allowMissingFields: false` + `Optional` per field
- Composition root: `ContentElementFieldSerializer` (injects all child serializers)
- Recursive: `ElementSlotsFieldSerializer` injects `ContentElementFieldSerializer` for tree serialization; `ElementSlotsFieldSerializer` is registered `lazy="true"` to break the `ContentElementFieldSerializer` ↔ `ElementSlotsFieldSerializer` circular dependency
- `ContentElementListFieldSerializer` overrides `normalize` to seed the element types' primitive defaults into the layout payload via the injected `Layout/LayoutDefaultSeeder` (the `IdFieldSerializer::normalize` precedent). It runs before `PreWriteValidationEvent`, handles both `ContentElement` and raw-array payloads, and never overwrites an authored value
- Infrastructure only — used in `ContentLayoutDefinition`, not domain API
