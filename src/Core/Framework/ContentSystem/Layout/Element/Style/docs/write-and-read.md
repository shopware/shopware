# Write Path, Read Path, and Output

## Strict Write, Registry-Free Read

`Layout/Codec/` is the boundary, one class per direction. Only the write path reads the registry; `StoredTreeConstraints` derives the validation constraints fresh per write and reuses that one built tree across every element in the write:

- **Write is strict, per flag.** An unknown option key, or a value that violates the option's derived constraints (`type` / `enum` / `range` / `maxLength`), is rejected. The codec rejects an unknown breakpoint key before the normalizer runs. The shape is also enforced per `breakpointAware`, but only over what `ElementStyleNormalizer` leaves: it runs first and broadcasts a breakpoint-aware option sent as a bare scalar across every breakpoint, so the descriptor only ever judges the canonical map. A flat option sent as a breakpoint map has no such canonical form and is rejected. `StoredTreeConstraints::styleConstraints()` composes the flag with the breakpoint-unaware constraint deriver: a breakpoint-aware option becomes a per-breakpoint `Collection`, a flat one a single `Optional($valueConstraints)`. Constraint derivation reads the strict `registry->all()`, so a cross-loader name collision fails the write and install paths hard.
- **Read is registry-free and structural.** `StoredElementCodec::decodeStyle()` never consults the registry: a scalar value is kept flat, an array value must be a non-empty breakpoint map (each key a `Breakpoint`, each value a scalar), and an empty map or an unknown breakpoint key throws. This is unambiguous because every value type is a primitive. Unknown option names pass through verbatim. A layout written while a plugin or app option was registered still renders after that provider is removed, and a cross-loader name collision never reaches the read path. This mirrors the element type system's unknown-`component` handling — kept verbatim on read, tolerated at resolve, rejected only on write. Re-saving such a layout is rejected until the orphaned option is cleared.

The Symfony constraints and the introspection schema are both derived from the one declaration, so the two cannot diverge.

## Output

A per-element `ElementStyle` passes through the system without any per-operation awareness: the mutation primitives carry it across every structural edit. The full, decomposed and skeleton formats emit it; the data (properties-only) format serves no structure and therefore no style. In every format `style` is omitted when empty, so it never serializes as an empty object.
