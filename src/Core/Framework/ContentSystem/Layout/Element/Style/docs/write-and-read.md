# Write Path, Read Path, and Output

## Strict Write, Registry-Free Read

`Layout/Codec/` is the boundary, one class per direction. Only the write path reads the registry; `StoredTreeConstraints` derives the validation constraints fresh per write and reuses that one built tree across every element in the write:

- **Write is strict, per flag.** An unknown option key, or a value that violates the option's derived constraints (`type` / `enum` / `range` / `maxLength`), is rejected. The codec rejects an unknown breakpoint key before the normalizer runs. The shape is also enforced per `breakpointAware`, but only over what `ElementStyleNormalizer` leaves: it runs first and broadcasts a breakpoint-aware option sent as a bare scalar across every breakpoint, so the descriptor only ever judges the canonical map. A flat option sent as a breakpoint map has no such canonical form and is rejected. `StoredTreeConstraints::styleConstraints()` composes the flag with the breakpoint-unaware constraint deriver: a breakpoint-aware option becomes a per-breakpoint `Collection`, a flat one a single `Optional($valueConstraints)`. Constraint derivation reads the strict `registry->all()`, so a cross-loader name collision fails the write and install paths hard.
- **Read is registry-free and structural.** `StoredElementCodec::decodeStyle()` never consults the registry: a scalar value is kept flat, an array value is cleaned into a canonical breakpoint map (each key a `Breakpoint`, each value a scalar). Nothing malformed is repaired: an unknown breakpoint key and an explicitly empty map both throw, see [What Each Direction Throws](#what-each-direction-throws). This is unambiguous because every value type is a primitive. Unknown option names ride through verbatim. A layout written while a plugin or app option was registered still renders after that provider is removed, and a cross-loader name collision never reaches the read path. This mirrors the element type system's unknown-`component` handling — kept verbatim on read, tolerated at resolve, rejected only on write. Re-saving such a layout is rejected until the orphaned option is cleared.

The Symfony constraints and the introspection schema are both derived from the one declaration, so the two cannot drift.

## What Each Direction Throws

`decodeStyle()` rejects rather than repairs, so nothing is silently dropped: `invalidMapKey` for a non-string option name, `unknownStyleBreakpoint` for an unknown breakpoint key, and `invalidFieldValueType` for a value that is neither scalar nor array, for a non-scalar breakpoint value, and for an explicitly empty breakpoint map.

Because the codec runs first on every request and column payload, a row carrying a malformed style shape — reachable only by a raw-SQL or migration write, or by a DAL write predating the codec — now throws on read instead of being cleaned.

`Diagnostics/ViolationCode::UnknownStyleOption` (intrinsic, error) names the case where the write only rejects. The diagnose and draft mutation routes carry the code in their 200 diagnostics; the preview route answers 400 with the message rather than the code; the DAL write — and therefore the persisted mutation routes, which commit before diagnosing — rejects at the serializer's `encode()` constraint, before the diagnostics gate runs.

## Output

A per-element `ElementStyle` rides through the system without any per-operation awareness: the mutation primitives carry it across every structural edit. The full, decomposed and skeleton formats emit it; the data (properties-only) format serves no structure and therefore no style. In every format `style` is omitted when empty, so it never serializes as an empty object.
