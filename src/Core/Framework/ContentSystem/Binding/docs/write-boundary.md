# Attribution and Diagnostics at the Write Boundary

What happens to a binding's bookkeeping and its stored wiring once the element leaves the mutation layer.

## Attribution Honesty at the Write Boundary

`AttributionReconciler` re-derives every element's `attributedSpecifications` at the single DAL write chokepoint for the `layout` field (the same seam `Layout/LayoutDefaultSeeder` occupies), so a persisted attribution is honest by construction: an entry survives a write only while the element's current wiring for that key still equals what the attributed specification's binding for that key produces (compared via `Hydration/DataLoader/ConfigCanonicalizer`, the canonicalized encoded config). It takes a stored element forest only, recursing every slot's children; the raw Admin/Sync JSON shape is absorbed earlier, by `Layout/Codec/StoredTreeCodec` at `Layout/Field/StoredElementListFieldSerializer::normalize`, before the boundary runs. A key whose wiring has since diverged — or whose specification or binding no longer exists — is silently dropped, never flagged as an error; a user who hand-edits a key's wiring away from the specification simply loses that key's attribution, and every other key keeps its own independently. This is a drop-not-throw seam by design: attribution is bookkeeping, not a constraint the write should fail over.

## Diagnostics Tie-Ins

A stored binding's wiring is also visible to the resolution and diagnostics kernel, independent of the response-layer concerns covered in [introspection.md](introspection.md):

- `Resolution/CandidateOrigin::Stored` — a stored `DataRequirement` whose produced type resolves and is assignable to the declared reference FQCN becomes the property's `resolved` pick directly, never a `candidates` menu entry (applied wiring is a resolution, not an offer).
- `Diagnostics/ViolationCode::MismatchedReferenceType` — a stored wiring whose produced type is **not** assignable to the declared FQCN is an intrinsic-scope error, independent of any bound root source.
