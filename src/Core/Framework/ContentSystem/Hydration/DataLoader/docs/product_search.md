# Product Search Loader (`source: "product_search"`)

Loads the product listing for a search term — the result page behind the shop's search box.

```json
{
  "id": "search-results",
  "component": "Sw:Product:Listing",
  "properties": {
    "searchTerm": "{{search}}"
  },
  "dataRequirements": {
    "listing": {
      "source": "product_search",
      "config": {
        "searchTermProperty": "searchTerm",
        "associations": ["cover"]
      }
    }
  }
}
```

Config fields:
- `searchTermProperty` (optional) - Property on this element holding the search term. Defaults to `"searchTerm"`
- `associations` (optional) - List of associations to load with the products
- `associationOverride` (optional) - Names an element property holding a `list<string>` of further associations. `LoaderInputResolver` merges that list into `associations` before `load()` runs. Defaults to the property name `"associations"`

Filters, sorting, and pagination cannot be steered from the query string here. The loader calls the search route with a request carrying only the search term, so every render uses the route defaults: page 1, `core.listing.productsPerPage` products, and the sorting configured in `core.listing.defaultSearchResultSorting`. [`product_listing`](product_listing.md) differs — it forwards the incoming request.
