# Stored model

The stored model is `StoredElement`, the element a layout persists, and `StoredTree`, the tree that holds it. The model is two element classes joined by one explicit conversion.

## `StoredElement` and `RenderedElement` are two classes joined by one explicit conversion

The split applies the single responsibility principle to the element. `StoredElement`, which a layout persists, is a different type from `RenderedElement`, which a page serves. `StoredElement` carries data requirements, context wiring, attribution and property values in wrapped form. `RenderedElement` carries a flat map of raw values and no wiring. The two classes meet in the render step only. No element class plays both roles. Nothing writes into an element while the element renders. A listener replaces the tree through its event instead of editing the tree it received. A consumer that needs the content of a rendered element descends into it explicitly. Every edit of either class produces a new instance through a `with*()` method, and each class copies its fields at one private site. The module does not trade the `final readonly` immutability of the stored classes away to save a copy.

Why: One mutable element class served the storage, validation, mutation, wire and template roles. Hydration events handed listeners that mutable element, and in-place mutation became an extension contract. A rebuild that copied fields by hand dropped any field added to the class later.

Not chosen: One element class that storage, validation, the administration, templates and several pipeline steps all use, or an in-place write that saves a copy. A rendered element that extends `Struct`, which reopens mutable extension state and generic traversal, and which a readonly class cannot extend.

In code:

- `RenderedElementFactory` mints every rendered element from a stored one inside the render step.
- `create()` mints in full rendering.
- `createStructural()` mints for the skeleton.
- A listener replaces the tree through `ContentTreePreparationEvent::replaceTree()`.
- `RenderedElementFactoryTest` pins the conversion.
- `StoredElementTest` pins the stored side's immutability.
- See [stored-and-rendered.md](../stored-and-rendered.md).

## Every map key is a string, and the module rejects an integer-castable key, never coerces it

Every key of a property, slot, data-requirement, wiring or placeholder map is a string that PHP cannot cast to an integer. The decode gate rejects an integer-castable key as a client defect with `INVALID_MAP_KEY`. The `StoredElement` and `RenderedElement` constructors reject an integer-castable key in the maps they build.

Why: PHP casts the array key `"5"` to `5`. PHP also renumbers integer keys when it merges arrays. No storage layer can therefore keep an integer-castable key sound.

Exceptions: The ban is flat over the top-level maps. A list value keeps its integer keys. The value walk never recurses into keys.

In code:

- The `StoredElement` and `RenderedElement` constructors throw on an integer key.
- `StoredElementWiringDecoder::decodeConsumers()` throws on an integer key in a wiring map.
- `PlaceholderValues::from()` throws on an integer key in a placeholder map.
- See [client-defect-codes.md](../client-defect-codes.md).

## The module seeds a primitive default at write time, never at serve time

Creation and replacement write the declared default of a primitive property in the stored shape. A binding writes the input defaults of its specification. On every write, the DAL write boundary seeds any type default that is still absent. It never seeds over a present value, including an authored null. Serving and diagnostics read only stored values. A stored value therefore satisfies a required primitive, and the declaration never does.

Why: A serve-time default makes an unfilled required property look filled. It also lets a later declaration change what stored layouts render.

In code:

- `AbstractLayoutMutation::primitiveDefaults()` seeds defaults.
- `BindingApplicator` seeds defaults.
- The write-boundary `LayoutDefaultSeeder` seeds defaults and fills only absent keys.
- `LayoutDiagnostics` reads no default.
- `ContentLayoutDefaultSeedingTest` pins that a plain DAL create gets its primitive default seeded.
- See [layout-write-gates.md](../layout-write-gates.md).

## The creating write sets the root source of a layout

The root source of a layout never changes after the creating write. A layout row receives its root source in the write that creates the row. The write gate proves a stored layout resolvable against the one root context of its root source. `ContentLayoutAssignmentWriteValidator` rejects an assignment to an entity of another kind. The module does not support a layout polymorphic over root sources.

Why: Settling the root source once lets the write gate prove a layout resolvable at write time instead of on every render. A polymorphic layout would require the write gate to prove it resolvable against every candidate root context.

Not chosen: Resolving the root source per request from the referencing entity, or reusing one layout across kinds.

In code:

- `ContentLayoutDefinition::ROOT_SOURCE_FIELD` carries the `Required` and `Immutable` flags.
- `ContentLayoutWriteValidator` proves a stored layout resolvable against the root source in that field.
- `ContentLayoutAssignmentWriteValidator` rejects a mismatch.
- See [Validation/README.md](../../Validation/README.md).

## An element id is an opaque string, unique across all roots of a layout

An element id carries no format. No consumer derives meaning from the shape of an element id. The decode gate that every write path and every draft path share rejects two values only. The first value is the reserved virtual-root literal, which would collide with the render wrapper. The second value is a string that PHP casts to an integer, which the [map-key rule](#every-map-key-is-a-string-and-the-module-rejects-an-integer-castable-key-never-coerces-it) bans because element ids serve as map keys. A repeated element id is a well-formedness violation.

Why: A format constraint invites a consumer to parse the id.

Not chosen: A 32-character hex pattern for the id.

In code:

- `StoredElementCodec::decode()` rejects `VirtualRootWrapper::VIRTUAL_ROOT_ID` and an integer-castable id with `INVALID_ELEMENT_ID`.
- `StoredTree::validate()` reports `ViolationCode::DuplicateElementId`.
- `StoredElementCodecStructuralDecodeTest` pins that decode rejects the virtual-root literal and an integer-castable id.
- See [client-defect-codes.md](../client-defect-codes.md).

## Also true by construction

- The type declaration and the mint select a rendered element's keys, so storage alone never brings a key to a template: [output-schema.md](../../Layout/Type/docs/output-schema.md)
- A JSON-decoded stored value is a scalar, null, list or map of stored values, and objects exist only in `RenderedElement`: [stored-and-rendered.md](../stored-and-rendered.md)
- `RenderedElement` is a closed value object outside the framework struct hierarchy, so a walk over framework structs never enters it: [stored-and-rendered.md](../stored-and-rendered.md)
