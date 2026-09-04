# Provider Configuration

Configuration reference for the `providesContext` entry an element declares to expose data to its children.

Provider exposes data as context for direct children using `providesContext`.

```json
{
  "id": "product-detail-provider",
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
  }
}
```

Fields:
- Context key (`"product"`) - Name consumers use to reference this context
- `type` - Context data type:
  - `"single"` - Single entity/value
  - `"collection"` - Array of entities/values
- `distribution` - How data is distributed to direct children:
  - `"broadcast"` - All children receive same data
  - `"indexed"` - Children receive data by position
  - `"keyed"` - Children receive data by matching their property to data keys (see `keyProperty`)
  - `"sliced"` - Data split into chunks for each child (see `sliceSize`)
  - `"iterator"` - One item per child, distributed sequentially
- `consumerAlias` (optional) - Renames context key for child elements. Allows reusable components to work with different data sources without modification. Optional on input; on output a `providesContext` entry always carries `consumerAlias` (`null` when not aliased).

**Strategy-specific fields:**

- `keyProperty` (keyed only, optional) - Element property name used for key matching. Defaults to `"data_key"`. Each child's property at this name is matched against the data keys.
- `sliceSize` (sliced only, optional) - Number of items per chunk. Defaults to `10`. Must be at least 1; a lower value is rejected on write.

Note: The context key in `providesContext` typically matches a property name loaded by `dataRequirements`.

**Consumer Alias Example:**

**Use case:** You have reusable product card components that expect data as `"product"`. Your homepage loads featured products as `"featuredProducts"`, but you want to use the same product cards without modifying them.

```json
"providesContext": {
  "featuredProducts": {
    "type": "collection",
    "distribution": "indexed",
    "consumerAlias": "product"
  }
}
```

The provider loads data as `featuredProducts`, but child components receive it as `product`. This lets you reuse the same product card in homepage (featuredProducts), categories (categoryProducts), and search (searchResults) - all expecting `product` internally.
