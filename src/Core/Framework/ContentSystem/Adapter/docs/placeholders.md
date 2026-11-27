# Placeholders

Default placeholders available in entity-based rendering:
- `{{productId}}` - Product UUID (product endpoint)
- `{{categoryId}}` - Category UUID (category endpoint)
- `{{landingPageId}}` - Landing page UUID (landing-page endpoint)

Use these in element properties and data requirements. See [Product Detail Page Example](../../docs/product-detail-page.md) for usage.

## Additional Parameters

A query parameter becomes a placeholder only when the specification source that renders the page declares it in `placeholderQueryParameters()`.
The product source declares `referrerCategoryId`, which the breadcrumb uses to build its path through the category the visitor came from.
The category and landing page sources declare none.

`RenderingSpecificationFactory` adds the declared parameters for every source, so a source derives its own placeholder values from the route alone.
Only scalar query values become placeholders.
A query parameter never replaces a placeholder the source derived, such as the entity id.
Parameters listed in `shopware.http_cache.ignored_url_parameters`, such as `utm_source`, never become placeholders, even when declared, because they do not distinguish HTTP cache entries.
The original request keeps every parameter for analytics and other request consumers.

**Query string example:**

```
/store-api/content/product/abc123?referrerCategoryId=def456
```

Makes `{{referrerCategoryId}}` available as a placeholder, for example in the breadcrumb's loader config:

```yaml
resolvedBy:
  breadcrumb:
    referrerCategoryProperty: "{{referrerCategoryId}}"
```

**Declaring a parameter in your own source:**

```php
final class BlogPostSpecificationSource extends AbstractSpecificationSource
{
    public function placeholderQueryParameters(): array
    {
        return ['commentPage'];
    }
}
```

A placeholder value is inserted as the request sent it, without escaping.
Declare only the parameters a layout needs, and prefer passing them to a loader that validates them over placing them in element text.
