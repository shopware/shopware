# Wire contract

The wire contract is the shape of what the module accepts and serves over the Admin API and the Store API.

## The stored element's wire shape is defined once, by one paired codec

The storage column and every Admin API element body share one codec with round-trip symmetry. One class holds decode and encode together, over a fixed key set in fixed order. Decode throws on an unknown key and on a loader source with no registered config serializer. Decode strips neither.

Why: Two serializations of one element diverge. A codec that strips input instead of throwing drops it silently.

Exceptions: The Store API serves `RenderedElement` to shoppers without attribution.

In code:

- `StoredElementCodec::encode()` delegates to `StoredElement::jsonSerialize()`.
- `StoredTreeCodec` and `MutationResponse` call the codec.
- `StoredElementCodecStructuralDecodeTest` pins that decode throws on an unknown top-level key instead of stripping it.
- `StoredElementCodecDataRequirementTest` pins that decode throws, naming the element, on a data requirement with an unregistered source.
- See [Layout/Field/README.md](../../Layout/Field/README.md).

## The Admin API exchanges `StoredElement`

Authoring clients read and write the `StoredElement` shape end to end, typed by the [administration's one element type](clients.md#the-administration-declares-one-element-type-typed-from-the-wire). Shoppers receive the `RenderedElement` shape, and no API accepts a `RenderedElement` as input. The Store API schemas describe exactly what a client can call, and the module deletes any schema definition that no route reaches.

Why: A client deriving storage keys duplicates server logic and diverges from it.

Not chosen: One element class on both APIs, leaving storage derivation to the client.

In code:

- The Admin API exchanges elements through `StoredElementCodec`.
- See [stored-and-rendered.md](../stored-and-rendered.md).

## The module encodes its responses

Every framework struct in a body passes the protection gate leaf by leaf. Module encoders write the full, decomposed and data bodies from the render result in one listener, after SEO enrichment and before framework encoding. In-process consumers read the typed page, never an encoded body. No other object may hide a framework struct.

Why: `StructEncoder::encode()` recurses into a `Struct` and passes every other object through raw, so a non-`Struct` object that holds a `Struct` would carry it past the field filter and publish every field. If a module encoder took over field filtering, nothing would enforce that filtering.

Not chosen: An open value domain for rendered properties.

Exceptions: The skeleton format stays a struct that the framework encodes. Only `ContentSkeletonElement::fromRendered()` builds that struct.

In code:

- `ContentResponseEncodingListener` writes the full, decomposed and data bodies.
- `ContentPageEncoder` hands `Struct` leaves to `StructEncoder::encode()`.
- The `RenderedElement` constructor enforces that no other object hides a struct.
- `StoreApiSeoResolver` reads the `properties` and `slots` of a `RenderedElement` in a dedicated branch, because no generic `Struct` walk reaches them, and a rename of either field changes that branch.
- See [Output/README.md](../../Output/README.md).

## Every response format is a structural projection of one rendered forest

The module generates its routes as formats times section resolvers. Every full-mode response wraps one page over one forest. A cached skeleton and a later data response join on the element id, never on a ref. A ref is local to one response, and the module numbers refs in document order. The module assigns data to positions deterministically, so a client may cache the skeleton first.

Why: A client outside this codebase composes the data response onto a cached skeleton. The client cannot place data for an element that the skeleton lacks.

Exceptions: The skeleton puts the element alias on root nodes only. The decomposed format puts the element alias on every node. A skeleton node and a decomposed node are therefore not interchangeable.

In code:

- `ContentRouteCompilerPass` generates the routes.
- Every full-mode response builds its page through `ContentPage::fromRenderResult()`.
- `ResolvedValueIndexFactory` numbers the refs in document order.
- `ContentRouteRenderingTest` pins that a data response assigns only to element ids the skeleton response carries.
- See [Output/README.md](../../Output/README.md).

## A PHP name never sets the wire key behind a module-owned encoder

On a struct that the framework encodes, a property name is a wire key. Behind a module-owned encoder, a PHP name is wire-inert.

Why: A positional swap or a rename can compile, type-check and pass every unit test while it moves data between response channels.

In code:

- `ContentPageEncoder` writes its keys and `ContentPageEncoder::ELEMENT_API_ALIAS` as literals.
- `ContentSkeletonPage` reaches the wire through `StructEncoder`.
- `ContentRouteRenderingTest` pins that the wire carries `id`, `name` and `version`, never the struct's `layoutId`-prefixed names.
- See [Output/README.md](../../Output/README.md).

## Structural malformation and registry drift are different cases on read

Decode throws on a malformed persisted row, on read as on write. A well-formed row that names a style option or an element type the registry no longer holds still decodes. Decode keeps that unknown name verbatim. A row that names a removed style option still renders.

Why: Uninstalling a plugin must not make layouts unreadable. A row that no supported path produces is corruption that an operator sees.

Exceptions: Decode throws instead on a data requirement whose loader source lost its config serializer.

In code:

- `StoredElementListFieldSerializer::decode()` uses `StoredElementCodec::decodeStyle()`, which is structural and registry-free.
- `RenderedElementFactory` declares nothing for an unregistered component.
- `StoredElementCodecStructuralDecodeTest` pins the malformed row.
- `StoredElementCodecDataRequirementTest` pins the de-registered source.
- See [write-and-read.md](../../Layout/Element/Style/docs/write-and-read.md).
