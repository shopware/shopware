# Entity Collection Loader (`source: "entity_collection"`)

Loads multiple entities by their IDs.

```json
{
  "id": "product-slider",
  "component": "Sw:Product:Slider",
  "properties": {
    "productIds": ["019456789abc", "019456789def", "019456789ghi"]
  },
  "dataRequirements": {
    "products": {
      "source": "entity_collection",
      "config": {
        "entity": "product",
        "property": "productIds",
        "associations": ["cover"]
      }
    }
  }
}
```

Config fields:
- `entity` (required) - Entity name to load
- `property` (required) - Property on this element containing an array of entity IDs
- `associations` (optional) - List of associations to load with the entities

After loading, access via element's `products` property (the requirement key).
