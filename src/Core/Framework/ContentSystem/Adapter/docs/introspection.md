# Entity Type Introspection

The Admin API endpoint a client reads to discover which entity types a content layout can be assigned to and previewed against. It is a `GET`, returns JSON, requires Admin API auth, and is served by `Framework/Api/Controller/InfoController`.

`GET /api/_info/content-system-entity-types.json`

The entity types a layout can be assigned to and previewed against (`product`, `category`, `landing_page`, plus any custom assignable entity). Backed by `Adapter/RootSourceRegistry::entityRootSources()`, whose entity-type id list is baked in at build time by `ContentLayoutAssignableCompilerPass`.

Response:

```json
{
  "entityTypes": ["product", "category", "landing_page"]
}
```

A flat array of DAL entity names that support content layout assignment. The values returned here are exactly the `entityType` values the preview endpoint accepts.

Full field-level schema: [content-system-entity-types.json](../../../Api/ApiDefinition/Generator/Schema/AdminApi/paths/content-system-entity-types.json).
