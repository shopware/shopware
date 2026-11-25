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
      "illustration": null,
      "category": null,
      "priority": 0,
      "copilot": { "summary": "...", "hints": ["..."] },
      "properties": {
        "<propertyName>": {
          "type": "string",
          "contextTypes": ["single"],
          "translatable": false,
          "enum": null,
          "default": null,
          "required": true,
          "mappable": false,
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
        "<translatablePropertyName>": { "kind": "property", "type": "string", "required": false, "translatable": true },
        "<referenceStorageKey>": { "kind": "resolvedByStorage", "type": "string", "required": true }
      },
      "defaultBindingRootSources": null
    }
  ],
  "styleOptions": {},
  "breakpointFallbacks": {
    "xs": ["sm", "lg"],
    "sm": ["lg"],
    "md": ["lg"],
    "lg": [],
    "xl": ["lg"],
    "xxl": ["xl", "lg"]
  }
}
```

`source` is `core`, `bundle:<name>`, `plugin:<name>`, or `app:<name>`.
A property `type` is a primitive name (`string`, `boolean`, `integer`, `number`), `object`, `array`, an FQCN for hydrated data, or a list of types.
Objects describe named members through `properties`; arrays describe their entries through `items`.
These nested declarations describe values but do not recursively validate stored values.

`icon` is the Meteor icon name used by the sidebar tree and as the picker fallback.
`illustration` is the independent SVG filename without its extension, or `null` when none is declared.

`priority` is always an integer, defaulting to `0` when the type declaration omits `meta.priority`.
The Administration uses higher priorities first within each element picker category, with label and type name as tie-breakers.

`mappable` says whether the property may be mapped.
`contextTypes` derives accepted candidate cardinalities from `type`.
Primitives and non-`Collection` classes accept `single`; `array` and `Collection` classes accept `collection`; bare `object` accepts both.
A union combines its members' kinds: `string|integer` accepts only `single`, while a union mixing collection and non-collection classes accepts both.
The client filters a candidate's `contextType` against this list; the server still checks effective-type assignability.

Each type entry carries a folded `bindingSpecifications` catalogue.
The response also carries a top-level `styleOptions` catalogue and a `breakpointFallbacks` map.
Each breakpoint maps to an ordered list of fallback keys.
Clients check the field's own value first, then use the first set value in the list.
See [Style options](../../Element/Style/docs/introspection.md) and [Binding specifications](../../../Binding/docs/introspection.md).

## `storageSchema`

`properties` publishes the [hydrated](output-schema.md) output schema: what a rendered element of this type carries. `storageSchema` publishes the other half, what an element of this type **stores**, keyed by stored key. `Layout/Type/StoredSchemaResolver` derives it per type. It encodes as `{}` for a type that stores nothing. See [The Admin API exchanges `StoredElement`](../../../docs/principles/wire-contract.md#the-admin-api-exchanges-storedelement).

Each entry is `{ kind, type, required }` plus an optional `default` and an optional `translatable`, and `kind` says where the key comes from:

| `kind` | The stored key is | `type` is |
| --- | --- | --- |
| `property` | a declared primitive property, stored under its own key | the primitive type name |
| `resolvedByStorage` | the storage key of a `resolvedBy` reference property, taken from the type's synthesized default specification | `string` or `list<string>` |
| `config` | a reference token a binding's loader config names | `string` or `list<string>` |

A declared FQCN property gets no entry at all: nothing is stored under the reference key itself, only under its `resolvedBy` storage key.
Declared `object`, `array`, and union properties also get no `property` entry, even when their values are stored under their own keys.

On `resolvedByStorage` and `config`, `type` is the config key's `referencedType`, not the type of the reference token. The token is always a string naming a property, while the value stored under it may be a list of ids. Neither carries a `default`: a config key's default is a default *token* (a property name), never a default stored value. Only a `property` entry has a `default`, and only when the declared property has one.

`translatable` is present, and always `true`, only on a `property` entry whose declared property is translatable, and is omitted everywhere else. It tells a client that the stored value under that key is a [language map](../../../docs/principles/README.md#glossary) of values of `type`, not a bare value. `default` stays the declared scalar, and storage seeds it under the system language id, `Defaults::LANGUAGE_SYSTEM`. The hydrated `properties` schema publishes the same flag for every property as a plain boolean.

One key claimed by more than one kind yields exactly one entry, by precedence `property` > `resolvedByStorage` > `config`. A declared property is the most specific statement about a stored key. Between `resolvedByStorage` and `config`, the `resolvedBy` shorthand's own storage key is the more specific.

Full field-level schema: [content-system-element-types.json](../../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-element-types.json).

A custom element type registered by a plugin or app ([Custom Element Types](custom-types.md)) appears here once registered.

## `defaultBindingRootSources`

`defaultBindingRootSources` is always present and names the root sources on which an insert that names no binding specification succeeds.
`null` means the type has no default or its default places no root-source restriction.
A list holds root-source ids, the ids that key `mappingCandidates` in `GET /api/_info/content-system-mapping-candidates.json`, in the order the server lists known root sources.
An empty list means such an insert fails on every root source, because the type has more than one default or the scoped entries of its default share no root source.
The field does not describe an insert that names a binding specification, which can succeed on root sources the list excludes.

On a preset, `GET /api/_info/content-system-layout-presets.json` carries the same field, derived over the compiled payload with nested presets expanded.
It is the intersection over every element of the preset of the root sources on which that element's default resolves, with keys an element already wires excluded.
It is `null` when no element's default carries a scoped value in an unwired entry and no element's type has more than one default, so nothing restricts the root source.
It is `[]` when no root source satisfies all of them, or when an element's type has more than one default, even when no default is scoped.
