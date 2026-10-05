# Binding Specification Introspection

The specifications for each type are folded into the `bindingSpecifications` key on each entry of [`content-system-element-types.json`](../../Layout/Type/docs/introspection.md) (`InfoController::elementTypeSchema()`), keyed by source-qualified id: the id a client passes back as `bindingSpecificationId`.

## Binding specifications

The specifications are filtered to the entry's type, backed by the binding specification registry (`Binding/Registry`) and serialized via `BindingSpecification::toSchema()`. A client passes these ids back as `bindingSpecificationId` to the bind-element and insert-element actions and derives the applicable ones from `bindingSpecifications[element.component]`.

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

`source` follows the same convention as element types and style options (`core`, `bundle:<name>`, `plugin:<name>`, `app:<name>`). `resolves` is keyed by the reference property it wires. `inputs` is keyed by the primitive property it seeds a default into (an entry without a `default` key means the property is left to the caller). Both encode as `[]` when the specification declares none. Every `inputs` entry always carries a `required` flag, derived by the server from the specification's wiring, never authorable, marking a property that is read through a required config key of a wiring whose reference property is itself required.

The server derives storage keys and publishes them on the same entry as [`storageSchema`](../../Layout/Type/docs/introspection.md#storageschema), so a client never parses `resolves` config ([data-loading.md](../../docs/principles/data-loading.md#an-authoring-client-reads-loader-configuration-through-introspection-and-never-parses-it)). The storage key of a `resolvedBy` reference property appears there with `kind: "resolvedByStorage"`, every other reference token a wired loader names with `kind: "config"`.

`default: true` marks a type's synthesized default (`id === type`), derived and never authored: [default-specification.md](default-specification.md). `InsertElement` and `ReplaceElement` fill-apply it at scaffold and replace time with no client action, see [Api/docs/mutation-binding.md](../../Api/docs/mutation-binding.md) ("Automatic default application").

Full field-level schema: [content-system-element-types.json](../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-element-types.json).
