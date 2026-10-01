# Until first release

The principle in this area holds only until the module's first tagged release, when a contributor deletes this file. The model is that the unreleased module changes a stored row in place and keeps no compatibility shim.

## The unreleased module accommodates no old data

The unreleased module promises no compatibility. A stored row changes in place, with no migration and no read-side repair. A stored row that a tightened rule now rejects becomes unreadable. The module keeps no compatibility shim and no risk mitigation. A contributor may add an abstract method to a subclassable class while every implementation of that class is first-party.

Why: Accommodating data that no released version produced is a permanent cost for a transient state.

Not chosen: Migrating old rows, or promising compatibility before release.

In code:

- `AbstractResponseFactory::collectsValueIndex()` is abstract.
- Only first-party classes implement that method.
- `StoredElementCodecStructuralDecodeTest` pins that the codec rejects a malformed stored shape instead of repairing the shape.
- See [Layout/Field/README.md](../../Layout/Field/README.md).
