> Conceptual overview and design rationale live in [README.md](README.md), same
> directory. The references and constraints below cover most code changes; read
> the README when you need the mental model.

## Constraints

- Validation: Symfony `Collection` constraint with `allowMissingFields: false` + `Optional` per field
- Composition root: `ContentElementFieldSerializer` (injects all child serializers)
- Recursive: `ElementSlotsFieldSerializer` injects `ContentElementFieldSerializer` for tree serialization; `ElementSlotsFieldSerializer` is registered `lazy="true"` to break the `ContentElementFieldSerializer` ↔ `ElementSlotsFieldSerializer` circular dependency
- `StoredElementListFieldSerializer` overrides `normalize` as the write's first decode, ahead of `PreWriteValidationEvent`: it decodes the payload into a `Layout/StoredTree` (either payload shape — elements the caller already built, or raw arrays through `Layout/Codec/StoredTreeCodec`), rejects a tree whose `StoredTree::validate()` reports a violation (`invalidLayoutStructure`, duplicate element id), admits it through `Layout/LayoutWriteBoundary::apply()`, re-encodes to the arrays the DAL constraint pass and `encode()` expect, and memoizes the admitted tree on the write `Context` as a `Layout/LayoutWriteContext` keyed by entity name + id, so `Validation/ContentLayoutWriteValidator` gates that tree instead of decoding the column again. The memo removes the gate's decode, not `encode()`'s: a normal write decodes twice, in `normalize` and again in `encode`. A `ContentSystemException` raised anywhere in that chain is remapped to a `WriteConstraintViolationException` (`ContentSystemException::layoutWriteRejection()`), the same remapping `encode` applies
- `StoredElementListFieldSerializer::encode` decodes the write payload through `Layout/Codec/StoredTreeCodec` instead of passing it through, so the codec's rules are write-time rules. A node-local defect it raises — an unknown key, a malformed container, an unregistered loader source — is remapped to a `WriteConstraintViolationException` (via `ContentSystemException::layoutWriteRejection()`) whose violation code is that defect's own `CONTENT_SYSTEM__*` error code, and so is rejected here rather than reaching `PreWriteValidationEvent` as an `invalid_config` violation
- Infrastructure only — used in `ContentLayoutDefinition`, not domain API
