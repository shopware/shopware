# Product Suggest Loader (`source: "product_suggest"`)

Loads the short suggestion listing shown while a visitor types in the search box. Same shape as [product_search](product_search.md), but the suggest route returns a smaller, unfiltered result.

```json
{
  "id": "search-suggest",
  "component": "Sw:Product:Suggest",
  "properties": {
    "searchTerm": "{{search}}"
  },
  "dataRequirements": {
    "suggestions": {
      "source": "product_suggest",
      "config": {
        "searchTermProperty": "searchTerm"
      }
    }
  }
}
```

Config fields:
- `searchTermProperty` (optional) - Property on this element holding the search term. Defaults to `"searchTerm"`
- `associations` (optional) - List of associations to load with the products
- `associationOverride` (optional) - Names an element property holding a `list<string>` of further associations. `LoaderInputResolver` merges that list into `associations` before `load()` runs. Defaults to the property name `"associations"`
