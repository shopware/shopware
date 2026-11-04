# Automatic Data Loading

Entity-based rendering automatically loads the main entity before rendering your layout -- no `dataRequirements` declaration needed -- whenever an element on the page reads it through a root-scoped consumer. The entity ID is available via placeholders, and the entity object is loaded with pre-configured associations and available as the layout's [root context](../../docs/principles/README.md#glossary). A page-level requirement that no element reads is not loaded, so it runs no query and contributes no cache tag.

**Auto-loaded entities and associations:**

The entity class is the SALES-CHANNEL one wherever the entity has a sales-channel definition, because `EntityLoader` loads through the sales-channel repository when one is registered. A mapping provider reading the root receives that sales-channel entity and advertises its effective output type in the candidate's `valueType`.

| Endpoint                                          | Entity            | Context Key    | Pre-loaded Associations                                                                             |
|---------------------------------------------------|-------------------|----------------|-----------------------------------------------------------------------------------------------------|
| `/store-api/content/product/{productId}`          | SalesChannelProductEntity | `product` | `manufacturer.media`, `options.group`, `properties.group`, `mainCategories.category`, `media.media`, `cover.media` |
| `/store-api/content/category/{categoryId}`        | SalesChannelCategoryEntity | `category`     | `media`, `translations`                                                                             |
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
