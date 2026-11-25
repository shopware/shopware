# Product Review Loader (`source: "product_review"`)

Loads the reviews of a product as a paginated search result. Page and filters come from request parameters, not from config.

```json
{
  "id": "product-reviews",
  "component": "Sw:Product:Reviews",
  "properties": {
    "productId": "{{productId}}"
  },
  "dataRequirements": {
    "reviews": {
      "source": "product_review",
      "config": {
        "property": "productId",
        "associations": []
      }
    }
  }
}
```

Config fields:
- `property` (optional) - Property on this element holding the product ID. Defaults to `"productId"`
- `associations` (optional) - List of associations to load with the reviews
- `associationOverride` (optional) - Names an element property holding a `list<string>` of further associations. `LoaderInputResolver` merges that list into `associations` before `load()` runs. Defaults to the property name `"associations"`
