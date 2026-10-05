# Element Type Introspection

The endpoint is a `GET`, returns JSON, requires Admin API auth, and is served by `Framework/Api/Controller/InfoController`.

`GET /api/_info/content-system-element-types.json`

The registered element types (components) that may be placed in a layout, each with its property schema, slots, and context contract. Backed by the element-type registry (`Layout/Type/Registry`), serialized via `ContentSystemElementTypeSpecification::toSchema()`.

Response:

```json
{
  "types": [
    {
      "name": "Sw:Product:Card",
      "label": "Product Card",
      "description": "...",
      "source": "core",
      "icon": null,
      "category": null,
      "copilot": { "summary": "...", "hints": ["..."] },
      "properties": {
        "<propertyName>": {
          "type": "string",
          "translatable": false,
          "enum": null,
          "default": null,
          "required": true,
          "title": "...",
          "description": "...",
          "adminUI": null
        }
      },
      "slots": [
        { "name": "actions", "maxElements": 3, "allowList": ["Sw:Content:Button"], "description": "..." }
      ],
      "storageSchema": {
        "<propertyName>": { "kind": "property", "type": "string", "required": true },
        "<referenceStorageKey>": { "kind": "resolvedByStorage", "type": "string", "required": true }
      }
    }
  ]
}
```

`source` is `core`, `bundle:<name>`, `plugin:<name>`, or `app:<name>`; a property `type` is a primitive name (`string`, `boolean`, `integer`, `number`) or an FQCN for hydrated data. Each entry additionally carries a folded `bindingSpecifications` catalog, and the response carries one top-level `styleOptions` catalog covering every type — see [Style options](../../Element/Style/docs/introspection.md) and [Binding specifications](../../../Binding/docs/introspection.md).

## `storageSchema`

`properties` publishes the [hydrated](output-schema.md) output schema: what a rendered element of this type carries. `storageSchema` publishes the other half, what an element of this type **stores**, keyed by stored key. `Layout/Type/StoredSchemaResolver` derives it per type. It encodes as `{}` for a type that stores nothing. See [The Admin API exchanges `StoredElement`](../../../docs/principles/wire-contract.md#the-admin-api-exchanges-storedelement).

Each entry is `{ kind, type, required }` plus an optional `default`. `kind` says where the key comes from:

| `kind` | The stored key is | `type` is |
| --- | --- | --- |
| `property` | a declared primitive property, stored under its own key | the primitive type name |
| `resolvedByStorage` | the storage key of a `resolvedBy` reference property, taken from the type's synthesized default specification | `string` or `list<string>` |
| `config` | a reference token a binding's loader config names | `string` or `list<string>` |

A declared FQCN property gets no entry at all: nothing is stored under the reference key itself, only under its `resolvedBy` storage key.

On `resolvedByStorage` and `config`, `type` is the config key's `referencedType`, not the type of the reference token. The token is always a string naming a property, while the value stored under it may be a list of ids. Neither carries a `default`: a config key's default is a default *token* (a property name), never a default stored value. Only a `property` entry has a `default`, and only when the declared property has one.

One key claimed by more than one kind yields exactly one entry, by precedence `property` > `resolvedByStorage` > `config`. A declared property is the most specific statement about a stored key. Between `resolvedByStorage` and `config`, the `resolvedBy` shorthand's own storage key is the more specific.

Full field-level schema: [content-system-element-types.json](../../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-element-types.json).

A custom element type registered by a plugin or app ([Custom Element Types](custom-types.md)) appears here once registered.
