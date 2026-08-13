# Entity Loader (`source: "entity"`)

Loads a single entity by ID or property reference.

```json
{
  "id": "product-detail",
  "component": "Sw:Product:Detail",
  "dataRequirements": {
    "product": {
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product",
        "associations": ["manufacturer", "cover", "categories"]
      }
    }
  }
}
```

Config fields:
- `entity` (required) - Entity name to load (e.g., `"product"`, `"category"`)
- `property` (required) - Property on this element containing the entity ID. The loader reads the ID from here.
- `associations` (optional) - List of associations to load with the entity

After loading, access via element's `product` property (the requirement key).

A product card that loads its own data:

```json
{
  "id": "product-card",
  "component": "Sw:Product:Card",
  "properties": {
    "product": "{{productId}}"
  },
  "dataRequirements": {
    "product": {
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product"
      }
    }
  }
}
```

The `property` field points to the element's own property. The loader reads the ID from there, loads the product, and stores the result back in the same property.

If multiple elements need the same product, load it once at their parent and use the context system instead (see [Context System](../../../Layout/Element/Context/README.md)).
