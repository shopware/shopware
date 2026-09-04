# Context Example

A single provider distributing one loaded entity to three consumer children.

Provider distributing context to multiple consumer children:

```json
{
  "id": "product-detail-context",
  "component": "Sw:Product:Detail",
  "dataRequirements": {
    "product": {
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product"
      }
    }
  },
  "providesContext": {
    "product": {
      "type": "single",
      "distribution": "broadcast"
    }
  },
  "slots": {
    "default": [
      {
        "id": "product-title",
        "component": "Sw:Product:Title",
        "acceptsContext": {
          "product": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "product-price",
        "component": "Sw:Product:Price",
        "acceptsContext": {
          "product": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "product-images",
        "component": "Sw:Product:Images",
        "acceptsContext": {
          "product": {
            "type": "single",
            "required": true
          }
        }
      }
    ]
  }
}
```

Process:
1. Provider loads product via `dataRequirements`
2. Provider exposes product as `"single"` context with `"broadcast"` distribution
3. All three children (`title`, `price`, `images`) receive the same product data
4. Each consumer declares context as `required: true`
