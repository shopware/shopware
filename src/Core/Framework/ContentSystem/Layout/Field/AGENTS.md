## Constraints

- Declare `Field/` serializers only on `EntityDefinition` classes, and keep them out of the domain API. Check: does code outside `ContentLayoutDefinition` reference the field or its serializer? [README.md](README.md)
- Keep conformance independent of `LayoutGate::SKIP_VALIDATION_STATE`: `StoredElementListFieldSerializer`, `LayoutWriteBoundary` and the `StoredTreeConstraints` descriptor never read it. Check: does any of the three read the skip state? [write-admission.md](../../docs/principles/write-admission.md)

## Where to look

- The column field, the serializer, its constraint building and cache override, and the `normalize` write hook: [README.md](README.md#key-classes)
