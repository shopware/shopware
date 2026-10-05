# Write admission

Write admission is the path that a layout write takes into storage through `LayoutWriteBoundary::apply()`, the write boundary. The model is a single choke point that runs its passes in a fixed order.

## Every layout write passes through the write boundary, a single choke point

Every route that persists a layout commits through `LayoutWriteBoundary::apply()`, including plain DAL writes, the Sync API, imports and fixtures. The write boundary runs its passes in a fixed order. No pass overwrites a value the author set. The passes reconcile attribution. No client-side sanitizer runs before the write boundary, under the [server-owned value rule](values.md#every-rule-about-a-value-is-the-servers-and-the-client-sends-the-raw-value).

Why: Two write boundaries diverge.

Not chosen: Per-route validation and normalization.

Exceptions: Raw SQL and migrations bypass the write boundary, with the obligations that the [bypass rule](#no-dal-write-can-bypass-the-constraint-descriptor) states.

In code:

- `StoredElementListFieldSerializer::normalize()` passes every layout tree through `LayoutWriteBoundary::apply()`.
- `ContentLayoutDefaultSeedingTest` pins that a plain DAL create, bypassing the mutation operations, still passes the write boundary's seeder.
- See [layout-write-gates.md](../layout-write-gates.md).

## The order of the write passes is part of the contract

Seeding and normalization run before the validation event. `ContentLayoutWriteValidator` checks root-source membership before resolvability. Every rule states its position in the order. The first rule reported for a given input is therefore deterministic. A new pass declares where it sits in the order.

Why: The pass order determines which of two defects a client sees first. The pass order also determines whether the validator reads a seeded value at all.

In code:

- Inside `StoredElementListFieldSerializer::normalize()`, `LayoutWriteBoundary::apply()` seeds type defaults, normalizes style and reconciles attribution, in that order.
- These passes run before `PreWriteValidationEvent`.
- `ContentLayoutWriteValidator` checks well-formedness first, then membership with an early return, then resolvability.
- See [Validation/README.md](../../Validation/README.md).

## No DAL write can bypass the constraint descriptor

The `StoredTreeConstraints` descriptor runs on every DAL write of a layout tree, whatever the skip state. Only raw SQL and migrations reach storage past the descriptor. A writer that reaches storage past the descriptor must seed required defaults itself. That writer must also write the stored shape itself.

Why: A skip flag that also skipped the descriptor would let a fixture store a shape that every later render throws on.

In code:

- Only `PreWriteValidationEvent` subscribers read `LayoutGate::SKIP_VALIDATION_STATE`.
- `StoredElementListFieldSerializer` therefore still passes every tree through `LayoutWriteBoundary` and the `StoredTreeConstraints` descriptor.
- The descriptor includes `PropertyTypeConformance`.
- `ContentLayoutWriteMemoLifetimeTest` shows that a DAL write under the skip state still passes the write boundary and leaves no tree behind in `LayoutWriteContext`.
- See [layout-write-gates.md](../layout-write-gates.md).

## Also true by construction

- The write gate checks the oldest tree that the field serializer recorded for its row in `LayoutWriteContext`. The write gate consumes that tree on the skip path too and never decodes the column: [Layout/Field/README.md](../../Layout/Field/README.md)
