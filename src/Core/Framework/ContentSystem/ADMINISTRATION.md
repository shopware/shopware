# Administration Integration

Admin API introspection over the entity types a content layout can be assigned to and previewed against. This is the `GET /api/_info/content-system-entity-types.json` endpoint the Administration (and admin API clients such as AI-assisted layout generators) read before assembling a layout.

**Scope:** the Admin API entity-type introspection endpoint only. The other introspection catalogs live with the systems they describe: element types in [Layout/Type/docs/introspection.md](Layout/Type/docs/introspection.md), style options in [Layout/Element/Style/docs/introspection.md](Layout/Element/Style/docs/introspection.md), binding specifications in [Binding/docs/introspection.md](Binding/docs/introspection.md), and data loaders (`content-system-data-loaders.json`) in [Hydration/DataLoader/README.md](Hydration/DataLoader/README.md). The preview, resolve-and-diagnose, mutation, and persisted mutation endpoints — their request/response contract and error model — are indexed in [Api/README.md](Api/README.md). Store API rendering routes (`/store-api/content*`) are covered in [USAGE.md](USAGE.md) and [SalesChannel/README.md](SalesChannel/README.md). Registering new specification sources and event listeners is covered in [EXTENSION.md](EXTENSION.md); element types in [Layout/Type/docs/custom-types.md](Layout/Type/docs/custom-types.md), style options in [Layout/Element/Style/docs/custom-options.md](Layout/Element/Style/docs/custom-options.md), data loaders in [Hydration/DataLoader/docs/custom-loaders.md](Hydration/DataLoader/docs/custom-loaders.md), and binding specifications in [Binding/README.md](Binding/README.md).

## Introspection Endpoints

These are all `GET`, return JSON, require Admin API auth, and are served by `Framework/Api/Controller/InfoController`. They are introspection over what is registered at build time, so their content grows as plugins and apps register more types (see [EXTENSION.md](EXTENSION.md)).

The response envelope shown under each endpoint below is the human summary. The authoritative, field-level response schema is the OpenAPI path file linked at the end of each section.

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
