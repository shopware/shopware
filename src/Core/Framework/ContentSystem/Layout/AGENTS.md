## Navigation

- Why these constraints hold, and what was not chosen (stored model): [stored-model.md](../docs/principles/stored-model.md)
- Why both paths share one codec, and why the codec and the descriptor stay mirrored: [drafts-and-gates.md](../docs/principles/drafts-and-gates.md)
- Why these constraints hold, and what was not chosen (context wiring): [context-wiring.md](../docs/principles/context-wiring.md)
- Why the value rules hold, and what was not chosen: [values.md](../docs/principles/values.md)
- Why one codec serves storage and the Admin API, and what a read keeps: [wire-contract.md](../docs/principles/wire-contract.md)
- Why a layout write has one admission point and a fixed pass order: [write-admission.md](../docs/principles/write-admission.md)

## Constraints

- Multi-root layouts: element-provided context is tree-scoped, not layout-scoped. Root-ambient context is the exception and is layout-scoped by construction
- `Scaffolding/VirtualRootWrapper` carries the page-level placeholder values and the wrap/unwrap prune scaffolding, and nothing else: no providers, no consumers, no data requirements. It distributes nothing to the roots in its slot
- Treat an element id as an opaque string, unique across all roots: `StoredElementCodec::decode()` rejects only the reserved virtual-root literal and an integer-castable string. Check: does the change parse an id or narrow it to a pattern? `StoredElementCodecStructuralDecodeTest` pins the domain.
- Placeholders (`{{key}}`) resolved in single pass on the stored tree, in FULL mode only (`Scaffolding/StoredTreePreparer`)
- Keep the preparation and resolution order that [docs/pipeline-steps.md](../docs/pipeline-steps.md) owns. Check: `StoredTreePreparerTest` and `ElementLoweringTest` pin the order.
- Field/ serializers are infrastructure — only interact in EntityDefinition classes
- Seed a primitive default at write time, never at serve time: `LayoutDefaultSeeder` fills an absent key, never a present value, an authored null included. Check: does a serve or diagnostics path read a type default? `LayoutDefaultSeederTest` and `LayoutDiagnosticsTest` pin both halves.
- Keep the draft decode and the write on one `StoredTreeStyleNormalizer` service, beside the one codec below. Check: `DraftLayoutStyleParityTest` pins that the draft decode and the write produce the same style.
- Tighten `StoredElementCodec` and `StoredTreeConstraints` in one change. Check: `StoredTreeShapeConformanceTest` pins where the two agree.
- Check a written value, never repair it. No client may sanitize a value before sending it. Check: does the change add a server pass or a client step that rewrites, coerces, filters or drops a value?
- Keep one codec for the stored element: storage and every Admin API body go through `StoredElementCodec`.
- Keep the Admin API on the stored shape and the Store API on the rendered one. No stored-only key in a Store API body. No rendered value on the Admin API. Check: can the change put either into the wrong API?
- On read, throw on a malformed row but keep a name the registry no longer knows. Check: does a read path now reject a drifted option or component?
- Admit every layout write through `LayoutWriteBoundary`: add no second write boundary and no per-route normalization. Check: can any DAL route store a tree `LayoutWriteBoundary::apply()` did not produce?
- Keep the admission order and state where a new pass sits. Check: `StoredElementListFieldSerializerTest` pins the order.
