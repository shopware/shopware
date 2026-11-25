# Cross Selling Loader (`source: "cross_selling"`)

Loads the configured cross-selling groups of a product, each with its resolved products.

```json
{
  "id": "product-cross-selling",
  "component": "Sw:Product:CrossSelling",
  "properties": {
    "productId": "{{productId}}"
  },
  "dataRequirements": {
    "crossSelling": {
      "source": "cross_selling",
      "config": {
        "property": "productId",
        "associations": ["cover"]
      }
    }
  }
}
```

Config fields:
- `property` (optional) - Property on this element holding the product ID. Defaults to `"productId"`
- `associations` (optional) - List of associations to load with the cross-sold products
- `associationOverride` (optional) - Names an element property holding a `list<string>` of further associations. `LoaderInputResolver` merges that list into `associations` before `load()` runs. Defaults to the property name `"associations"`
