# Context Path Resolution

How a consumer addresses a nested property of the context an ancestor exposes, or of the layout's root-ambient context.

Consumers can request nested properties from context using dot notation. When a provider exposes an entity like `product`, consumers can access nested properties without loading the full entity themselves.

**Example**: Provider exposes product, consumer requests only the cover image:

```json
{
  "id": "product-provider",
  "component": "Sw:Product:Container",
  "dataRequirements": {
    "product": {
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product",
        "associations": ["cover", "manufacturer"]
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
        "id": "cover-image",
        "component": "Sw:Content:Image",
        "acceptsContext": {
          "product.cover": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "manufacturer-name",
        "component": "Sw:Content:Text",
        "acceptsContext": {
          "product.manufacturer.name": {
            "type": "single",
            "required": false
          }
        }
      }
    ]
  }
}
```

**Key points**:
- Provider exposes full `product` entity
- `cover-image` receives only `product.cover` (MediaEntity)
- `manufacturer-name` receives only `product.manufacturer.name` (string)
- Supports arbitrary nesting depth: `product.manufacturer.country.code`
- Works only with Shopware Struct objects (all DAL entities)
- Path resolution happens automatically during context delivery
- A `scope: "root"` consumer addresses the root-ambient context by the same rule: `product.cover` resolves through the root `product` struct exactly as it resolves through a delivered one

**Required vs Optional**:
- `required: true` - Throws exception if path cannot be resolved (property missing, intermediate null, non-Struct value)
- `required: false` - Returns null silently if path fails

**Benefits**:
- Reduces memory usage: elements receive only what they need
- Cleaner element APIs: no need to extract nested data in templates
- Type safety: path validated at runtime with clear error messages
