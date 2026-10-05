# Automatic Data Loading

Entity-based rendering automatically loads the main entity before rendering your layout -- no `dataRequirements` declaration needed. The entity ID is available via placeholders, and the entity object is loaded with pre-configured associations and available as the layout's [root context](../../docs/principles/README.md#glossary).

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

An element receives the auto-loaded entity by declaring `scope: "root"` on the matching context key. See [Context flows only between adjacent elements](../../docs/principles/context-wiring.md#context-flows-only-between-adjacent-elements). An element may re-expose a root-scoped key through its own `providesContext` entry, and what it passes down from there is ordinary element-provided context. Redistribution stays the mechanism for context an element itself provides (see [Context Redistribution](../../Layout/Element/Context/docs/redistribution.md)). Declare `dataRequirements` only for additional data beyond what's automatically loaded (e.g., cross-sell products, reviews).
