# Stored and Rendered Element Models

Which of the two element models a class is about is the sharpest subject distinction in this module, and a name carries it as a prefix on the subject. The naming principles this page applies live in [NAMING.md](../NAMING.md).

There is no unprefixed element subject: a name saying only "element" leaves the reader unable to tell which of the two contracts below applies, which is exactly the ambiguity the prefixes exist to remove.

**`Stored*`** is the storage, authoring, validation, mutation and admin-exchange side. It carries `dataRequirements`, context wiring (`contextDefinitions`) and `attributedSpecifications`, and its property values are wrapped in a value object, never raw and never a hydrated object. That wrapping makes "a loaded entity sitting in storage" a type error rather than a runtime check.

**`Rendered*`** is the render-time and Store-API-response side. It carries `id`, `component`, a flat `properties` map, `slots` and `style`, and its property values are raw PHP values, hydrated entities included. No data requirements, no wiring, no attribution: those authoring concerns finish their work before anything renders.

The two lists are exclusive in both directions. Adding wiring to a `Rendered*` subject or a hydrated object to a `Stored*` one is not an extension of the model, it is a name that has stopped being true.

## Where each subject belongs

So a contributor does not have to re-decide. These paths, and the examples on [Role suffixes](role-suffixes.md), are the names the storage/render split establishes; the code has yet to acquire some of them.

- `Layout/Element/StoredElement` — one authored element.
- `Layout/Element/StoredValue` — one wrapped property value.
- `Layout/StoredTree` — the forest of stored roots, and the algebra over it.
- `Rendering/RenderedElement` — one element as a response serializes it. It lives in `Rendering/`, with the classes that mint it, rather than under `Output/Struct/`, because it is not a `Struct` and does not pass through `StructEncoder`.
- `Output/Struct/ContentPage` — a rendered page.
- `Layout/Codec/` — the codecs between a wire shape and the stored model.

The prefix belongs to the element models, not to everything near them. A page, a violation, a registry, a source: none of these has two variants to tell apart, so none of them takes a prefix. Reach for `Stored`/`Rendered` when a reader could otherwise pick the wrong model, and not otherwise.
