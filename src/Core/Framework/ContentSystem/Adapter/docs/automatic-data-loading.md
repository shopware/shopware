# Automatic Data Loading

What an entity-based render loads before your layout runs, and how a layout takes delivery of it.

Entity-based rendering automatically loads the main entity before rendering your layout -- no `dataRequirements` declaration needed. The entity ID is available via placeholders, and the entity object is loaded with pre-configured associations and available via context to your layout's root elements.

**Auto-loaded entities and associations:**

| Endpoint                                          | Entity            | Context Key    | Pre-loaded Associations                                                                             |
|---------------------------------------------------|-------------------|----------------|-----------------------------------------------------------------------------------------------------|
| `/store-api/content/product/{productId}`          | ProductEntity     | `product`      | `manufacturer.media`, `options.group`, `properties.group`, `mainCategories.category`, `media.media` |
| `/store-api/content/category/{categoryId}`        | CategoryEntity    | `category`     | `media`, `translations`                                                                             |
| `/store-api/content/landing-page/{landingPageId}` | LandingPageEntity | `landing_page` | (none)                                                                                              |

**Usage example:**

```json
{
  "id": "product-page",
  "component": "Sw:Grid",
  "acceptsContext": {
    "product": {
      "type": "single",
      "required": true,
      "redistribute": true
    }
  },
  "slots": {
    "default": [
      {
        "id": "product-title",
        "component": "Sw:Product:Title",
        "acceptsContext": {
          "product": {"type": "single", "required": true}
        }
      }
    ]
  }
}
```

Root elements accept the auto-loaded entity via context and redistribute to children using `redistribute: true`. Deeper descendants require redistribution at each container level (see [Context Redistribution](../../Layout/Element/Context/docs/redistribution.md)). Declare `dataRequirements` only for additional data beyond what's automatically loaded (e.g., cross-sell products, reviews).
