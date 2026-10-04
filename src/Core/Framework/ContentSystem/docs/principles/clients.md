# Clients

The clients are the administration's layout editor and the storefront templates that render a served layout. The model is one wire-derived element type in the administration and one shared element partial in the storefront.

## The administration declares one element type, typed from the wire

The administration declares one element type, `ContentElementNode`, with the members that `StoredElement::jsonSerialize()` emits. A second client type for the same shape is deleted, never kept in step. No caller casts the `layout` field, which the generated entity schema types as `Array<unknown>`. One typed entry point names the `layout` field as element nodes.

Why: Two client types for one wire shape drift apart. A cast at a call site asserts a shape that nothing checks.

Not chosen: A module-local type beside the wire-derived type, reconciled by an adapter at each call site.

In code:

- `ContentElementNode` is the only declaration of the element type.
- `createContentLayoutRepository()` holds the two casts.
- It throws on a present `layout` that is not an array.
- The `content-layout-repository.util` spec pins the typed read and the throw.
- See [stored-and-rendered.md](../stored-and-rendered.md).

## Declared type and presentation metadata choose an editor control, never the reverse

The presentation metadata names a control type, not a component name. The presentation metadata also names a picker's entity. The administration never derives a picker's entity from the property's type. A control's codec follows the declared contract with explicit precedence and fallback. The parent component translates storage keys. A control therefore reads and writes only the property key. The administration shows a field whose `visibleWhen` is malformed. No hint hides a field unconditionally.

Why: A control guessed from the type shows raw values or nothing. A control that reads and writes storage keys couples every widget to the binding model.

Not chosen: Presentation metadata that names a component, or a type-inferred control that overrides the presentation metadata.

In code:

- `getPropertyControlType()` chooses the control type and reads the presentation metadata first.
- `getEntityName()` returns the entity that the presentation metadata names.
- `getEntityMultiCodec()` chooses the codec from the declared contract.
- `isPropertyVisible()` returns true for a malformed `visibleWhen`.
- The parent `sw-experience-studio-element-settings` translates storage keys.
- The `element-settings.util` spec and the specs of the parent and child components pin the control, codec, visibility and storage-key rules.
- No test pins the entity rule.
- See [introspection.md](../../Layout/Type/docs/introspection.md).

## One shared partial renders every element

A type name is a component name. Every caller renders an element through `_element.html.twig`. `ElementTypeNameResolver` builds a type name from the path of the type's declaration and from its source prefix. That type name is the component name that the partial renders. The administration renders no element, and its preview is the [storefront's render](preview.md#the-editors-preview-shows-what-the-storefront-renders) of the draft.

Why: A block duplicated in two callers diverges. A name derived two ways reaches output under two spellings.

Not chosen: A block in each caller, or two naming schemes that nothing checks against each other.

In code:

- `Sw:Content:Page` and `Slot` are the two callers.
- The partial passes `element.component` to `component()`.
- `ElementTypeNameResolver` builds the name.
- `ContentLayoutStorefrontRenderTest` pins the render of a page root and of a slot child.
- See [architecture.md](../../Layout/Type/docs/architecture.md#naming-alignment-with-storefront-twig-components).
