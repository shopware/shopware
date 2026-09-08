# Automatic Data Loading

What an entity-based render loads before your layout runs, and how a layout takes delivery of it.

Entity-based rendering automatically loads the main entity before rendering your layout -- no `dataRequirements` declaration needed. The entity ID is available via placeholders, and the entity object is loaded with pre-configured associations and available as the layout's root-ambient context.

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
  "slots": {
    "default": [
      {
        "id": "product-title",
        "component": "Sw:Product:Title",
        "acceptsContext": {
          "product": {"type": "single", "required": true, "scope": "root"}
        }
      }
    ]
  }
}
```

An element takes delivery of the auto-loaded entity by declaring `scope: "root"` on the matching context key. That is the only route by which it reaches an element directly: with no ancestor providing that key, a `scope: "parent"` consumer of it receives nothing however shallow the element sits, and a `redistribute` chain never carries it. The container in between declares no wiring at all, and the same consumer works at any depth (see [Consumer Configuration](../../Layout/Element/Context/docs/consumers.md)). An element that root-consumes a key may of course re-expose it through its own `providesContext` entry, and what it hands down from there is ordinary element-provided context. Redistribution stays the mechanism for context an element itself provides (see [Context Redistribution](../../Layout/Element/Context/docs/redistribution.md)). Declare `dataRequirements` only for additional data beyond what's automatically loaded (e.g., cross-sell products, reviews).
