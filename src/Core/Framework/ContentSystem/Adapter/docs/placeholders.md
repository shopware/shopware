# Placeholders

The `{{key}}` values an entity-based render makes available, and how to add more from the query string.

Default placeholders available in entity-based rendering:
- `{{productId}}` - Product UUID (product endpoint)
- `{{categoryId}}` - Category UUID (category endpoint)
- `{{landingPageId}}` - Landing page UUID (landing-page endpoint)

Use these in element properties and data requirements. See [Product Detail Page Example](../../docs/product-detail-page.md) for usage.

## Additional Parameters

Beyond URL path segments, you can pass additional parameters to your layouts via query string. These parameters become available as placeholders throughout your layout.

**Query string example:**
```
/store-api/content/product/abc123?page=2&limit=24
```

Makes `{{page}}` and `{{limit}}` available as placeholders:

```json
{
  "id": "product-listing",
  "component": "Sw:Product:Listing",
  "properties": {
    "page": "{{page}}",
    "limit": "{{limit}}"
  }
}
```

**Common use cases:**
- Pagination parameters (`page`, `limit`)
- Filter values (`category`, `brand`, `priceRange`)
- Display preferences (`view`, `sort`)
- Feature flags (`showReviews`, `hidePrice`)
