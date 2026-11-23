# Clients

The clients are the administration's layout editor and the storefront templates that render a served layout.

## The administration declares one element type, typed from the wire

The administration declares one element type, `ContentElementNode`, with the members that `StoredElement::jsonSerialize()` emits. A second client type for the same shape is deleted, never kept in step. No caller casts the `layout` field, which the generated entity schema types as `Array<unknown>`. One typed entry point names the `layout` field as element nodes.

Why: A second type diverges when the wire gains a member. A cast compiles against any payload.

In code:

- `ContentElementNode` is the only declaration of the element type.
- `createContentLayoutRepository()` holds the two casts.
- It throws on a present `layout` that is not an array.
- The `content-layout-repository.util` spec pins the typed read and the throw.
- See [stored-and-rendered.md](../stored-and-rendered.md).

## Declared type and presentation metadata choose an editor control, never the reverse

The presentation metadata names a control type, not a component name. The presentation metadata also names a picker's entity. The administration never derives a picker's entity from the property's type. A control's codec follows the declared contract with explicit precedence and fallback. The parent component translates storage keys. A control therefore reads and writes only the property key. The administration shows a field whose `visibleWhen` is malformed. No hint hides a field unconditionally.

Why: A control guessed from the type shows raw values or nothing. A control that reads and writes storage keys couples every widget to the binding model.

In code:

- `getPropertyControlType()` chooses the control type and reads the presentation metadata first.
- `getEntityName()` returns the entity that the presentation metadata names.
- `getEntityMultiCodec()` chooses the codec from the declared contract.
- `isPropertyVisible()` returns true for a malformed `visibleWhen`.
- The parent `sw-experience-studio-element-settings` translates storage keys.
- The `element-settings.util` spec and the specs of the parent and child components pin the control, codec, visibility and storage-key rules.
- No test pins the entity rule.
- See [introspection.md](../../Layout/Type/docs/introspection.md).

## Translatable settings fields read and write only the selected language

The editor displays a translatable property's stored entry for the active frame's selected language.
Missing text translations stay blank without substituting another language's value or the declared default.
Displaying a field never creates or updates an entry in the stored language map.
Editing a translatable property writes only the selected language's entry and preserves every other language entry.

Why: Showing inherited text in an editable field hides which languages have their own translation.

In code:

- `resolveTranslatableFieldValue()` projects the selected entry without applying a declared default.
- `writeElementPropertyValue()` writes through `withLanguageEntry()` using the active frame's language id.
- The `sw-experience-studio-element-settings` language spec, `sw-experience-studio-translation-settings` panel spec, and `translatable-property-write` spec pin these reads and writes.
- Preview and Storefront rendering use [server language reduction](translation.md#language-reduction-happens-on-the-server-and-nothing-downstream-receives-a-language-map).

## One shared partial renders every element

A type name is a component name. Every caller renders an element through `Slot.html.twig`. `ElementTypeNameResolver` builds a type name from the path of the type's declaration and from its source prefix. That type name is the component name that the partial renders. The administration renders no element, and its preview is the [storefront's render](preview.md#the-editors-preview-shows-what-the-storefront-renders) of the draft.

Why: A block duplicated in two callers diverges. A name derived two ways reaches output under two spellings.

In code:

- `Sw:Content:Page` renders the root elements through `<twig:Slot>`, and a component with slots, such as `Sw:Grid:Container`, renders its slot children through it.
- `Slot.html.twig` passes `element.component` to `component()`.
- `ElementTypeNameResolver` builds the name.
- `ContentLayoutStorefrontRenderTest` pins the render of a page root and of a slot child.
- See [architecture.md](../../Layout/Type/docs/architecture.md#naming-alignment-with-storefront-twig-components).
