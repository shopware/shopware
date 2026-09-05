# Consumer Configuration

Configuration reference for the `acceptsContext` entry an element declares to receive data from an ancestor, or from the layout's root-ambient context.

Consumer receives context from ancestor provider using `acceptsContext`.

```json
{
  "id": "product-title-consumer",
  "component": "Sw:Product:Title",
  "acceptsContext": {
    "product": {
      "type": "single",
      "required": true
    }
  }
}
```

Fields:
- Context key (`"product"`) - Under `scope: parent`, matches the provider's context key (or its `consumerAlias`); under `scope: root`, matches a key of the layout's root-ambient context. Either way the match is the key itself or a dot path hanging below it (see [path-resolution.md](path-resolution.md))
- `scope` (optional, default `"parent"`) - Where the consumer takes its value from:
  - `"parent"` - The context an ancestor provides, delivered one hop at a time along the tree
  - `"root"` - The layout's root-ambient context, supplied by the bound root source. It reaches the element directly at any depth, with no wiring on any intermediate container, and a `redistribute` chain never carries it. Cannot be combined with `redistribute: true`
- `type` - Expected context data type:
  - `"single"` - Expects single entity/value
  - `"collection"` - Expects array of entities/values
- `required` - Whether context is mandatory:
  - `true` - Element fails if context unavailable
  - `false` - Element works without context
- `propertyAlias` (optional) - Renames the property key where context data is stored in this element. The consumed data is stored with this alias instead of the original context key. Useful for component reusability when elements expect specific property names. Cannot contain dots. Must be unique within the element (no two consumers can resolve to the same property key).

Consumer receives context data directly as a property.

**Property Alias Example:**

**Use case:** Reusable image element expects `"image"` property, but product layout provides `"product.cover"`. Use `propertyAlias` to adapt without modifying the element.

```json
{
  "id": "product-cover",
  "component": "Sw:Content:Image",
  "acceptsContext": {
    "product.cover": {
      "type": "single",
      "required": true,
      "propertyAlias": "image"
    }
  }
}
```

Element receives `product.cover` from parent context but stores it as `image` internally, matching what the renderer expects. Enables component reusability across different data sources.
