# Type declarations

A type declaration describes the properties and slots of an element type, and this area limits what it may state. The model is that a type declaration constrains what an instance may hold and decides nothing that the layout or the editor owns.

## Context wiring belongs to the element, never to the type

A stored element's `providesContext` and `acceptsContext` entries in the layout set which context that element provides and consumes. A type declares the properties that an instance may wire. A type declares no provider and no consumer. A reference property without `resolvedBy` therefore stays wirable in any layout.

Why: A consumer minted by the type would apply at scaffold time. At scaffold time, the element has no ancestors and no root context yet.

Exceptions: `resolvedBy` on a reference property is the one type-level offer of a default wiring. `DefaultBindingSpecificationSynthesizer` turns that `resolvedBy` offer into the type's default binding.

In code:

- `PropertySpecificationDto` has no wiring field.
- `ContextDistributor` reads each element's own `contextDefinitions`.
- See [output-schema.md](../../Layout/Type/docs/output-schema.md).

## Each type sets the required flag of a property for its own reason

Where a declared property states `required`, the value comes from the declaring type's own behaviour and never from a sibling type. An omitted `required` flag reads as optional. Required means that the element has no useful rendering without the value. An unresolved required property therefore blocks the write. An unresolved optional property passes the write. `LayoutDiagnostics` warns about an unresolved optional property only when no candidate can fill it.

Why: A wrong optional flag turns misplacement of the element into permanent emptiness behind a warning that nobody reads.

In code:

- `PropertySpecification::required()` carries the flag.
- `LayoutDiagnostics` reports `ViolationCode::UnresolvedRequired` as an error and `ViolationCode::UnresolvedOptional` as a warning.
- `LayoutDiagnosticsTest` pins the required case.
- See [Diagnostics/README.md](../../Diagnostics/README.md).

## Presentation hints belong to the editor, and the server never branches on them

The `adminUI` block on a type property or a style option is presentation metadata for the administration. The module bases no storage, validation or normalization rule on the content of the `adminUI` block. The declaration validator checks only that the `adminUI` block is well-formed. The declaration states a storage-relevant kind as its own typed key.

Why: The box-spacing normalization, now the box-spacing branch of `ElementStyleNormalizer`, once read the `adminUI` component name to choose its storage rule. Inferring a storage rule from an editor hint let a control choice govern persisted data. A change of control would then silently alter storage.

In code:

- `ElementStyleNormalizer` branches on the declared `StyleOptionSpecification::kind()` value `KIND_BOX_SPACING`.
- `StyleOptionSpecification::adminUI()` has no server-side caller.
- `TypedStyleOptionValidator` checks only the shape of the `adminUI` block.
- See [option-model.md](../../Layout/Element/Style/docs/option-model.md).

## Also true by construction

- Only a primitive or all-primitive union constrains a value member by member. A bare `object`, class reference or union carrying one admits any value: [PropertyTypeConformanceValidator](../../Layout/Codec/PropertyTypeConformanceValidator.php)
