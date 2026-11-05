# Stored model

The stored model is `StoredElement`, the element a layout persists, and `StoredTree`, the tree that holds it.

## `StoredElement` and `RenderedElement` are two classes joined by one explicit conversion

`StoredElement`, which a layout persists, is a different type from `RenderedElement`, which a page serves. `StoredElement` carries data requirements, context wiring, `attributedSpecifications` and property values in wrapped form. `RenderedElement` carries a flat map of raw values and no wiring. The two classes meet in the render step only. No element class plays both roles. Nothing writes into an element while the element renders. A listener replaces the tree through its event instead of editing the tree it received. Code that needs the content of a rendered element descends into it explicitly. Every edit of either class produces a new instance through a `with*()` method, and each class copies its fields at one private site.

Why: One mutable element class served the storage, validation, mutation, wire and template roles. Hydration events handed listeners that mutable element, and in-place mutation became an extension contract. A rebuild that copied fields by hand dropped any field added to the class later.

Decided against: A rendered element that extends `Struct`, which reopens mutable extension state and generic traversal, and which a readonly class cannot extend.

In code:

- `RenderedElementFactory` builds every rendered element from a stored one inside the render step.
- `create()` builds in [FULL mode](README.md#terms-that-are-easy-to-confuse).
- `createStructural()` builds in SKELETON mode.
- A listener replaces the tree through `ContentTreePreparationEvent::replaceTree()`.
- `StoredElementTest` pins the stored side's immutability.
- See [stored-and-rendered.md](../stored-and-rendered.md).

## Every map key is a string, and the module rejects an integer-castable key, never coerces it

Every key of a property, slot, data-requirement, wiring or placeholder map is a string that PHP cannot cast to an integer. `StoredElementCodec::decode()` rejects an integer-castable key as a [client defect](README.md#glossary) with `INVALID_MAP_KEY`. The `StoredElement` and `RenderedElement` constructors reject an integer-castable key in the maps they build.

Why: PHP casts the array key `"5"` to `5`. PHP also renumbers integer keys when it merges arrays. No storage layer can therefore keep an integer-castable key sound.

Exceptions: The ban is flat over the top-level maps. A list value keeps its integer keys. The value walk never recurses into keys.

In code:

- The `StoredElement` and `RenderedElement` constructors throw on an integer key.
- `StoredElementWiringDecoder::decodeConsumers()` throws on an integer key in a wiring map.
- `PlaceholderValues::from()` throws on an integer key in a placeholder map.
- See [client-defect-codes.md](../client-defect-codes.md).

## The module seeds a property default at write time, never at serve time

Creation and replacement write the property defaults of the element type in the stored shape. A binding writes the input defaults of its specification. On every write, the DAL [write boundary](README.md#terms-that-are-easy-to-confuse) seeds any property default whose key is still absent. It never seeds over a present value, including an authored null and a map that carries only some members. Serving and diagnostics do not apply declaration defaults; live mappings follow the [mapping outcome rules](data-mapping.md#admission-and-missing-data-are-separate-outcomes). A stored non-null value therefore satisfies a required primitive, and the declaration never does.

Why: A serve-time default makes an unfilled required property look filled. It also lets a later declaration change what stored layouts render.

Exceptions: `defaultMapping` initializes source wiring through mutation runners, not stored property values at the DAL boundary; [mapping initialization](data-mapping.md#initialization-preserves-authored-choices) governs it. A storefront component that callers outside stored layouts also render, such as `Sw:Grid:Container`, keeps a prop fallback for those callers. The fallback repeats the declared default exactly. A property whose editor can store a null keeps a null fallback, because `RenderedElementFactory` [drops an authored null](values.md#a-present-null-and-an-absent-key-are-different-states). A cleared value therefore never renders as the declared default.

In code:

- `StoredDefaultProvider` defines a property default as a primitive's declared default or the map of its nested members' defaults.
- `AbstractLayoutMutation::storedDefaults()` seeds defaults on creation and replacement, and `BindingApplicator` seeds input defaults.
- The write-boundary `LayoutDefaultSeeder` fills only an absent top-level key.
- `LayoutDiagnostics` reads no default.
- `ContentLayoutDefaultSeedingTest` pins that a plain DAL create gets its primitive default seeded.
- `LayoutDefaultSeederTest::testKeepsAuthoredNestedPropertyWithoutMergingDefaults` pins that a present partial map gets no member default.
- See [layout-write-gates.md](../layout-write-gates.md).

## The creating write sets the root source of a layout

The root source of a layout never changes after the creating write. A layout row receives its root source in the write that creates the row. The [write gate](README.md#terms-that-are-easy-to-confuse) proves a stored layout resolvable against the one [root context](README.md#glossary) of its root source. `ContentLayoutAssignmentWriteValidator` rejects an assignment to an entity of another kind. The module does not support a layout polymorphic over root sources.

Why: Settling the root source once lets the write gate prove a layout resolvable at write time instead of on every render. A polymorphic layout would require the write gate to prove it resolvable against every candidate root context.

Decided against: Resolving the root source per request from the referencing entity, or reusing one layout across kinds.

In code:

- `ContentLayoutDefinition::ROOT_SOURCE_FIELD` carries the `Required` and `Immutable` flags.
- `ContentLayoutWriteValidator` proves a stored layout resolvable against the root source in that field.
- `ContentLayoutAssignmentWriteValidator` rejects a mismatch.
- See [Validation/README.md](../../Validation/README.md).

## An element id is an opaque string, unique across all roots of a layout

An element id carries no format, and no code derives meaning from its shape. The module rejects the [virtual root](README.md#glossary) id `__page_context_root__`, an integer literal, and an id with a line terminator, so every non-empty id it accepts matches the published `ContentElementId` pattern. An integer literal that PHP casts to an integer would also break the [map-key rule](#every-map-key-is-a-string-and-the-module-rejects-an-integer-castable-key-never-coerces-it). A repeated element id is a well-formedness violation.

Why: A format constraint invites code to parse the id. An id that the server stores and the published pattern rejects locks a schema-validating client out of the layout that carries it.

Decided against: A 32-character hex pattern for the id.

In code:

- `ElementIdRule` rejects `VirtualRootWrapper::VIRTUAL_ROOT_ID`, an integer literal such as `0`, `-0` or `9223372036854775808`, and an id that contains LF, CR, U+2028 or U+2029.
- `StoredElementCodec::decode()` throws `INVALID_ELEMENT_ID` on an id that `ElementIdRule` rejects, and reads back an empty id. `StoredTreeConstraints` rejects an empty id on write.
- `StoredTree::duplicateElementIds()` finds a repeated id, and `LayoutDiagnostics` reports it as `ViolationCode::DuplicateElementId`.
- `ElementIdRuleTest` pins each rejection and that `012`, a tab and NEL stay accepted.
- See [client-defect-codes.md](../client-defect-codes.md).

## Also true by construction

- The type declaration and the conversion select a rendered element's keys, so storage alone never brings a key to a template: [output-schema.md](../../Layout/Type/docs/output-schema.md)
- A JSON-decoded stored value is a scalar, null, list or map of stored values, and objects exist only in `RenderedElement`: [stored-and-rendered.md](../stored-and-rendered.md)
- `RenderedElement` is a closed value object outside the framework struct hierarchy, so a walk over framework structs never enters it: [stored-and-rendered.md](../stored-and-rendered.md)
