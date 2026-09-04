# Attribution and Diagnostics at the Write Boundary

What happens to a binding's bookkeeping and its stored wiring once the element leaves the mutation layer.

## Attribution Honesty at the Write Boundary

`AttributionReconciler` re-derives every element's `attributedSpecifications` at the single DAL write chokepoint for the `layout` field (the same seam `Layout/LayoutDefaultSeeder` occupies), so a persisted attribution is honest by construction: an entry survives a write only while the element's current wiring for that key still equals what the attributed specification's binding for that key produces (compared via `Hydration/DataLoader/ConfigCanonicalizer`, the canonicalized encoded config). It takes a stored element forest only, recursing every slot's children. Where that forest comes from depends on the payload shape: `Layout/Field/StoredElementListFieldSerializer::tree()` runs `Layout/Codec/StoredTreeCodec::decode()` over raw element arrays, but takes a payload whose every entry is already a `StoredElement` as it is, skipping the codec. A caller that hands the DAL a built forest therefore reaches the boundary without the codec ever having seen it. A key whose wiring has since diverged — or whose specification or binding no longer exists — is silently dropped, never flagged as an error; a user who hand-edits a key's wiring away from the specification simply loses that key's attribution, and every other key keeps its own independently. Attribution is bookkeeping, so a comparison that returns "no longer honest" drops the entry rather than failing the write.

A comparison that cannot be made at all is a different answer, and the reconciler catches nothing to hide it. An element whose requirement source has no registered config serializer cannot be encoded, so its wiring cannot be judged honest or dishonest; that `configSerializerNotRegistered` escapes the reconciler and refuses the write. `Layout/Field/StoredElementListFieldSerializer::normalize` remaps it the same way it remaps every other `ContentSystemException` raised under the boundary — a `WriteConstraintViolationException` (HTTP 400) whose violation code is the defect's own `CONTENT_SYSTEM__*` code — so the caller is told the source is unregistered instead of receiving a stored element whose attribution was quietly discarded, indistinguishable from one nothing ever claimed.

## Diagnostics Tie-Ins

A stored binding's wiring is also visible to the resolution and diagnostics kernel, independent of the response-layer concerns covered in [introspection.md](introspection.md):

- `Resolution/CandidateOrigin::Stored` — a stored `DataRequirement` whose produced type resolves and is assignable to the declared reference FQCN becomes the property's `resolved` pick directly, never a `candidates` menu entry (applied wiring is a resolution, not an offer).
- `Diagnostics/ViolationCode::MismatchedReferenceType` — a stored wiring whose produced type is **not** assignable to the declared FQCN is an intrinsic-scope error, independent of any bound root source.
