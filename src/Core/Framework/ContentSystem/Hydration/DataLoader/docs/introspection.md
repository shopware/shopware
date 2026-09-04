# Data Loader Introspection

The Admin API read surface a client uses to discover which data sources a `dataRequirements` entry may use, and the shape it returns. It is a `GET`, returns JSON, requires Admin API auth, and is served by `Framework/Api/Controller/InfoController`.

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

Full field-level schema: [content-system-data-loaders.json](../../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-data-loaders.json).

A custom data loader ([Custom Data Loaders](custom-loaders.md)) appears here. Wildcard loaders (`entity`, `entity_collection`) enumerate the live definition registry inside `producibleTypes()`.
