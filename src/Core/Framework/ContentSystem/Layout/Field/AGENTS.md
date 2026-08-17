> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Validation: Symfony `Collection` constraint with `allowMissingFields: false` + `Optional` per field
- Composition root: `ContentElementFieldSerializer` (injects all child serializers)
- Recursive: `ElementSlotsFieldSerializer` injects `ContentElementFieldSerializer` for tree serialization; `ElementSlotsFieldSerializer` is registered `lazy="true"` to break the `ContentElementFieldSerializer` ↔ `ElementSlotsFieldSerializer` circular dependency
- `StoredElementListFieldSerializer` overrides `normalize` to run two write-boundary passes over the layout payload, ahead of `PreWriteValidationEvent`: it first seeds the element types' primitive defaults via the injected `Layout/LayoutDefaultSeeder` (the `IdFieldSerializer::normalize` precedent, never overwriting an authored value), then re-derives each element's `attributedSpecifications` via `Binding/AttributionReconciler` (drop-not-throw: an attribution whose wiring has since diverged is dropped, never an error). Both handle the `StoredElement` and raw-array payload shapes
- `StoredElementListFieldSerializer::encode` decodes the write payload through `Layout/Codec/StoredTreeCodec` instead of passing it through, so the codec's rules are write-time rules. A node-local defect it raises — an unknown key, a malformed container, an unregistered loader source — is remapped to a `WriteConstraintViolationException` (via `ContentSystemException::layoutWriteRejection()`) whose violation code is that defect's own `CONTENT_SYSTEM__*` error code, and so is rejected here rather than reaching `PreWriteValidationEvent` as an `invalid_config` violation
- Infrastructure only — used in `ContentLayoutDefinition`, not domain API
