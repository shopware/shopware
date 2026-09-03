# Binding Specification Introspection

The one read surface a client uses to discover which specifications apply to an element, and the shape it returns.

The specifications for each type are folded into the `bindingSpecifications` key on each entry of `content-system-element-types.json` (`InfoController::elementTypeSchema()`), keyed by source-qualified id — the id a client passes back as `bindingSpecificationId`. A client derives the specifications applicable to an element from `bindingSpecifications[element.component]` on that catalog.

## Binding specifications

The registered binding specifications — declared wirings of one element type's reference properties to data loaders, plus defaults for its primitive properties — folded into the `bindingSpecifications` key on each entry of [`content-system-element-types.json`](../../Layout/Type/docs/introspection.md), keyed by their source-qualified id (`source:id`) and filtered to that entry's type. Backed by the binding specification registry (`Binding/Registry`), serialized via `BindingSpecification::toSchema()`. These are the ids a client passes back as `bindingSpecificationId` to the bind-element and insert-element actions; a client derives the specifications applicable to an element from `bindingSpecifications[element.component]`.

A type entry's `bindingSpecifications` fold:

```json
{
  "bindingSpecifications": {
    "core:Sw:Media:Image": {
      "id": "Sw:Media:Image",
      "type": "Sw:Media:Image",
      "label": "Image",
      "default": true,
      "resolves": {
        "media": { "loader": "entity", "config": { "entity": "media", "property": "mediaId" } }
      },
      "inputs": []
    }
  }
}
```

`source` follows the same convention as element types and style options (`core`, `bundle:<name>`, `plugin:<name>`, `app:<name>`). `resolves` is keyed by the reference property it wires; `inputs` is keyed by the primitive property it seeds a default into (an entry without a `default` key means the property is left to the caller). Both encode as `[]` when the specification declares none. Every `inputs` entry always carries a `required` flag — derived by the server from the specification's wiring, never authorable — marking a property that is read through a required config key of a wiring whose reference property is itself required.

A client does not read storage keys out of `resolves` config. The server derives them and publishes them on the same entry, as [`storageSchema`](../../Layout/Type/docs/introspection.md#storageschema): the storage key of a `resolvedBy` reference property appears there with `kind: "resolvedByStorage"`, every other reference token a wired loader names with `kind: "config"`.

`default: true` marks a type's synthesized default — the specification a `media`-style `resolvedBy` reference property produces automatically, with an id equal to the type name itself (`id === type`). It is derived, never authored: no `bindings:` entry can set it, and an authored entry's id can never equal the type name (reserved for the default). At most one specification per type is ever `default`. `InsertElement` and `ReplaceElement` fill-apply a type's default at scaffold/replace time with no client action — see [Api/docs/mutation-binding.md](../../Api/docs/mutation-binding.md) ("Automatic default application").

Full field-level schema: [content-system-element-types.json](../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-element-types.json).
