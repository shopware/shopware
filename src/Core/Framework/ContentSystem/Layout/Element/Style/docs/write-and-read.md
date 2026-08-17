# Write Path, Read Path, and Output

The asymmetry between a registry-backed strict write and a registry-free structural read, and where the resulting value surfaces.

## Strict Write, Registry-Free Read

`Layout/Field/ElementStyleFieldSerializer` is the boundary. Only the write path reads the registry; it derives the validation constraints fresh per write (the parent serializer reuses that one built tree across every element in the write):

- **Write is strict, per flag.** An unknown option key, an unknown breakpoint, or a value that violates the option's derived constraints (`type` / `enum` / `range` / `maxLength`) is rejected. The shape is also enforced per `breakpointAware`, both directions: a breakpoint-aware option sent as a bare scalar is rejected, and a flat option sent as a breakpoint map is rejected. The field serializer composes the flag with the breakpoint-unaware constraint deriver: a breakpoint-aware option becomes a per-breakpoint `Collection`, a flat one a single `Optional($valueConstraints)`. Constraint derivation reads the strict `registry->all()`, so a cross-loader name collision fails the write and install paths hard.
- **Read is registry-free and structural.** `deserialize()` never consults the registry: a scalar value is kept flat, an array value is cleaned into a canonical breakpoint map (each key a `Breakpoint`, each value a scalar; an empty map is dropped). This is unambiguous because every value type is a primitive. Unknown option names ride through verbatim. A layout written while a plugin or app option was registered still renders after that provider is removed, and a cross-loader name collision never reaches the read path. This mirrors the element type system's unknown-`component` handling — kept verbatim on read, tolerated at resolve, rejected only on write. Re-saving such a layout is rejected until the orphaned option is cleared, so a normal edit round-trip no longer auto-clears it.

The Symfony constraints and the introspection schema are both derived from the one declaration, so the two cannot drift: `StyleOptionConstraintDeriver` turns a `StyleOptionValueType` into a `list<Constraint>` via the fluent `ConstraintBuilder`.

## Output

A per-element `ElementStyle` rides through the system without any per-operation awareness. The mutation primitives (`Mutation/AbstractLayoutMutation::rebuildNode` / `cloneWithNewIds`, and `Mutation/Op/ReplaceElement`) carry it across every structural edit. `ContentElement::jsonSerialize()` emits it for the full format, and `Output/Struct/ContentSkeletonElement` carries it so it survives the skeleton and decomposed formats; the data (properties-only) format omits it. In every format `style` is omitted when empty, so it never serializes as an empty object.
