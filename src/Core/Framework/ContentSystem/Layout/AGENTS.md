## Constraints

- Treat an element id as an opaque string, unique across all roots. Check: does the change parse an id or narrow it to a pattern? `StoredElementCodecStructuralDecodeTest` pins the domain. [stored-model.md](../docs/principles/stored-model.md)
- Keep `Scaffolding/VirtualRootWrapper` to the page-level placeholder values and the wrap/unwrap prune scaffolding. Check: does the change give it a provider, consumer or data requirement, or make it distribute to its roots? [README.md](README.md#subdirectories)
- Keep the preparation and resolution order. Check: does the change reorder a step? `StoredTreePreparerTest` and `ElementLoweringTest` pin the order. [context-wiring.md](../docs/principles/context-wiring.md)
- Seed a primitive default at write time, never at serve time: `LayoutDefaultSeeder` fills an absent key, never a present value, an authored null included. Check: does a serve or diagnostics path read a type default? `LayoutDefaultSeederTest` and `LayoutDiagnosticsTest` pin both halves. [stored-model.md](../docs/principles/stored-model.md)
- Keep the draft decode and the write on one `StoredTreeStyleNormalizer` service. Check: `DraftLayoutStyleParityTest` pins that the draft decode and the write produce the same style. [drafts-and-gates.md](../docs/principles/drafts-and-gates.md)
- Tighten `StoredElementCodec` and `StoredTreeConstraints` in one change. Check: `StoredTreeShapeConformanceTest` pins where the two agree. [drafts-and-gates.md](../docs/principles/drafts-and-gates.md)
- Check a written value, never repair it. No client may sanitize a value before sending it. Check: does the change add a server pass or a client step that rewrites, coerces, filters or drops a value? [values.md](../docs/principles/values.md)
- Keep one codec for the stored element. Check: does a storage or Admin API path bypass `StoredElementCodec`? [wire-contract.md](../docs/principles/wire-contract.md)
- Keep the Admin API on the stored shape and the Store API on the rendered one. No stored-only key in a Store API body. No rendered value on the Admin API. Check: can the change put either into the wrong API? [wire-contract.md](../docs/principles/wire-contract.md)
- On read, throw on a malformed row but keep a name the registry no longer knows. Check: does a read path now reject a stale option or component? [wire-contract.md](../docs/principles/wire-contract.md)
- Admit every layout write through `LayoutWriteBoundary`: add no second write boundary and no per-route normalization. Check: can any DAL route store a tree `LayoutWriteBoundary::apply()` did not produce? [write-admission.md](../docs/principles/write-admission.md)
- Keep the admission order and state where a new pass sits. Check: `StoredElementListFieldSerializerTest` pins the order. [write-admission.md](../docs/principles/write-admission.md)

## Where to look

- Element model and DAL definitions: [README.md](README.md#architecture)
- Scaffolding records and the virtual-root wrapper: [README.md](README.md#subdirectories)
- How `LayoutDefaultSeeder` walks a forest: [README.md](README.md#default-seeding)
- Multi-root layouts, and element-provided versus root-ambient context across roots: [README.md](README.md#multi-root-layouts)
- Preparation order, placeholder resolution and the partial prune: [pipeline-steps.md](../docs/pipeline-steps.md)
- Subdirectory guides: [Element](Element/README.md), [Field](Field/README.md), [Type](Type/README.md)
