# Administration Integration

Admin API introspection over the content system's registered building blocks: which element types may be placed in a layout, which data loaders a `dataRequirements` entry may use, which entity types a layout can be assigned to, and which universal style options exist. These are the `GET /api/_info/content-system-*.json` endpoints the Administration (and admin API clients such as AI-assisted layout generators) read before assembling a layout.

**Scope:** the Admin API introspection endpoints only. The preview, resolve-and-diagnose, mutation, and persisted mutation endpoints — their request/response contract and error model — are indexed in [Api/README.md](Api/README.md). Store API rendering routes (`/store-api/content*`) are covered in [USAGE.md](USAGE.md) and [SalesChannel/README.md](SalesChannel/README.md). Registering new building blocks (element types, data loaders, specification sources) is covered in [EXTENSION.md](EXTENSION.md). The `bindingSpecifications` fold each type entry carries is documented in [Binding/README.md](Binding/README.md).

## Introspection Endpoints

These are all `GET`, return JSON, require Admin API auth, and are served by `Framework/Api/Controller/InfoController`. They are introspection over what is registered at build time, so their content grows as plugins and apps register more types (see [EXTENSION.md](EXTENSION.md)).

The response envelope shown under each endpoint below is the human summary. The authoritative, field-level response schema is the OpenAPI path file linked at the end of each section.

### Element types

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
      ]
    }
  ]
}
```

`source` is `core`, `bundle:<name>`, `plugin:<name>`, or `app:<name>`; a property `type` is a primitive name (`string`, `boolean`, `integer`, `number`) or an FQCN for hydrated data. Each entry additionally carries the folded `styleOptions` and `bindingSpecifications` catalogs, omitted from the example above for brevity — see [Style options](#style-options) and [Binding specifications](Binding/docs/introspection.md).

Full field-level schema: [content-system-element-types.json](../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-element-types.json).

A custom element type registered by a plugin or app ([EXTENSION.md → Custom Element Types](EXTENSION.md#custom-element-types)) appears here once registered.

### Data loaders

`GET /api/_info/content-system-data-loaders.json`

The data sources a `dataRequirements` entry may use (`source` values such as `entity`, `entity_collection`, `product_listing`, `navigation`, …), each with its declared config keys and the capabilities it can produce. Backed by `Schema/ContentSystemDataLoaderSchemaGenerator::getSchema()`, assembled at runtime by `ContentSystemDataLoaderMapResolver` from each loader's `configSpecification()` and `producibleTypes()`.

Response:

```json
{
  "sources": {
    "<source>": {
      "configKeys": [
        { "name": "entity", "kind": "entityName", "type": "string", "required": true },
        { "name": "property", "kind": "propertyReference", "type": "string", "required": true },
        { "name": "associations", "kind": "literal", "type": "list<string>", "required": false, "default": [] }
      ],
      "types": [
        {
          "producedType": "<FQCN>",
          "configTemplate": { "entity": "product" },
          "genericParameters": ["<FQCN>"]
        }
      ]
    }
  }
}
```

`<source>` is the `dataRequirements` source value (`entity`, `product_listing`, …). `configKeys` is the source's declared `LoaderConfigSpecification`, in declaration order: `kind` names what the stored value means (`literal`, `propertyReference`, an element property whose stored value feeds the loader, or `entityName`, a registered DAL entity), `required` is intrinsic requiredness, and `default` is present only when the key declares one (a declared default may itself be `null`, distinct from no default at all). `types` pairs each produced type (the sales-channel class where one exists) with the `configTemplate` needed to produce it — the inferable config keys. The keys a caller must still supply for a given capability (the completion residue) are the required `configKeys` names minus the keys already covered by that capability's `configTemplate`; a client derives this the same way `ContentSystemDataLoaderMap::residualConfigKeysFor()` does on the kernel side, not carried directly in this response.

Full field-level schema: [content-system-data-loaders.json](../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-data-loaders.json).

A custom data loader ([EXTENSION.md → Custom Data Loaders](EXTENSION.md#custom-data-loaders)) appears here. Wildcard loaders (`entity`, `entity_collection`) enumerate the live definition registry inside `producibleTypes()`.

### Entity types

`GET /api/_info/content-system-entity-types.json`

The entity types a layout can be assigned to and previewed against (`product`, `category`, `landing_page`, plus any custom assignable entity). Backed by `Adapter/RootSourceRegistry::entityRootSources()`, whose entity-type id list is baked in at build time by `ContentLayoutAssignableCompilerPass`.

Response:

```json
{
  "entityTypes": ["product", "category", "landing_page"]
}
```

A flat array of DAL entity names that support content layout assignment. The values returned here are exactly the `entityType` values the preview endpoint accepts.

Full field-level schema: [content-system-entity-types.json](../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-entity-types.json).

### Style options

`GET /api/_info/content-system-style-options.json`

The registered universal style options — presentation attributes (alignment, span, spacing, display) settable on every element regardless of its type — keyed by their wire name. Backed by the style option registry (`Layout/Element/Style/Registry`), serialized via `StyleOptionSpecification::toSchema()`. The same options are folded into the `styleOptions` key on each entry of [`content-system-element-types.json`](#element-types).

Response:

```json
{
  "styleOptions": {
    "col-span": {
      "type": "integer",
      "enum": null,
      "range": { "min": 1, "max": 12 },
      "maxLength": null,
      "default": null,
      "adminUI": { "component": "number", "label": "Column Span" },
      "breakpointAware": true
    }
  }
}
```

`range` bounds `integer`/`number` options, `maxLength` bounds `string` options (a string with no declared `maxLength` defaults to 255); `default` is advisory only — an introspection/Admin pre-fill hint, never seeded into stored element JSON or applied at serve time. `breakpointAware` marks whether the option's value is set per breakpoint (`xs`, `sm`, `md`, `lg`, `xl`, `xxl`) or as a single flat scalar.

Full field-level schema: [content-system-style-options.json](../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-style-options.json).
