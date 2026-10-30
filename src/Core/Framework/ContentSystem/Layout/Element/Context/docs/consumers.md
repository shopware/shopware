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

## Ordinary root delivery

An ordinary root-scoped consumer has no typed `source` reference. If its ambient root is missing or null, no value is delivered, even when the consumer is required. Any otherwise available authored or loader-resolved value remains unaffected.

With a non-null ambient root, a dotted consumer such as `product.manufacturer.name` resolves the nested path. An optional consumer delivers a present `null` when the path cannot be traversed because a member is missing or an intermediate value is null or is not a `Struct`. A required consumer throws `contextPathNotResolvable` for those failures, aborting the content pipeline. A successfully reached terminal property containing null is delivered as null, even when the consumer is required. Delivered null overrides any authored or loader-resolved value for the destination property.

Whole-field mappings use their separate [mapping outcome rules](../../../../Mapping/README.md#stored-mappings-and-rendering). Request-level handling of rendering failures follows [runtime error handling](../../../../Mapping/README.md#runtime-error-handling).

## Mapping dependencies

A whole-field mapping is still a stored root-scoped consumer keyed by its destination property. Its typed `source` names the candidate the provider resolves; the source path also contributes a page-level loading dependency. It is delivered directly from root context at any tree depth and does not travel through parent providers.

An inline token in an opted-in string property is authored text, not an `acceptsContext` consumer. After the preparation event, the renderer parses authored tokens before placeholder substitution and carries their spans through loading. Their paths derive page-level dependencies for that render; they do not create stored consumer wiring. Tokens inserted by placeholder values remain literal. Ordinary consumers retain the [root-delivery distinction](#ordinary-root-delivery) between an absent ambient root and a present root with an unresolvable nested path. See [the mapping contract](../../../../Mapping/README.md) and [the render pipeline order](../../../../docs/pipeline-contract.md).
