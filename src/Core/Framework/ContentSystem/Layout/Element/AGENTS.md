## Constraints

- Tell a present null from an absent key: `StoredElement::property()` returns `null` only for an absent key and never throws. An authored null comes back with `isNull()` true. Check: does the change test `property($key) === null`? [values.md](../../docs/principles/values.md)
- Keep the two element models apart: no `dataRequirements`, `contextDefinitions` or `attributedSpecifications` on `RenderedElement`, no unwrapped value in a `StoredElement` property. Check: does the change put a member of one model on the other? `RenderedElementFactoryTest` pins the conversion between them. [stored-model.md](../../docs/principles/stored-model.md)
- Change a stored element only through a `with*()` method such as `StoredElement::withSlots()`. Never add a setter, drop `readonly`, or rebuild one by hand. Check: does a `new StoredElement(` take its arguments from another element? Use a with-method. [stored-model.md](../../docs/principles/stored-model.md)
- Reject an integer-castable map key, never coerce it: every property, slot, data-requirement and wiring map is string-keyed. Check: does the change build such a map from input without a constructor or decoder that throws `invalidMapKey`? [stored-model.md](../../docs/principles/stored-model.md)

## Where to look

- Which of the two element models a name is about: [stored-and-rendered.md](../../docs/stored-and-rendered.md)
- `StoredElement`, `StoredValue` and `RenderedTreeEditor`, including the slot-map shapes and how a property serializes: [README.md](README.md#key-classes)
- Which keys `RenderedElementFactory` creates, where `ValueProvenance` is recorded and what a `RenderedTreeFinalizationEvent` listener may hand back: [README.md](README.md#rendered-element-keys)
- Editing a rendered forest, and the slot-map constraint of `RenderedTreeEditor::mapNodes()`: [README.md](README.md#editing-a-rendered-forest)
- The JSON shape of an element as a layout author writes it, its slots and nesting: [authoring-elements.md](docs/authoring-elements.md)
- What the skeleton response keeps of an element: [Output/README.md](../../Output/README.md#response-formats); when `style` is omitted: [write-and-read.md](Style/docs/write-and-read.md#output)
- Context providers and consumers: [Context/README.md](Context/README.md); universal style options: [Style/README.md](Style/README.md)
