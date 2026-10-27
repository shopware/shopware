# Breadcrumb Loader (`source: "breadcrumb"`)

Loads the breadcrumb trail for a product or category.

```json
{
  "id": "product-breadcrumb",
  "component": "Sw:Breadcrumb",
  "properties": {
    "entityId": "{{productId}}"
  },
  "dataRequirements": {
    "breadcrumb": {
      "source": "breadcrumb",
      "config": {
        "property": "entityId",
        "type": "product"
      }
    }
  }
}
```

Config fields:
- `property` (optional) - Property on this element holding the entity ID the trail is built for. Defaults to `"entityId"`
- `type` (optional) - Which entity the ID names, passed through to the breadcrumb route. Defaults to `"product"`
- `referrerCategoryProperty` (optional) - Property holding a category ID that pins the trail to the path the visitor arrived through. Unset by default; the ID is lower-cased before use
