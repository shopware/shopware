# Entity-Based Rendering

The main content section: the Store API endpoints that render an entity, the assignment record that binds a layout to it, and the sales channel fallback that picks between assignments.

Products, Categories, and Landing Pages can render directly using ContentSystem layouts. This is the primary method for rendering entity-based pages.

**Endpoints:**

| Endpoint                                   | Description         |
|--------------------------------------------|---------------------|
| `GET /store-api/content/{path}`            | Full response       |
| `GET /store-api/content-decomposed/{path}` | Decomposed response |
| `GET /store-api/content-skeleton/{path}`   | Skeleton only       |
| `GET /store-api/content-data/{path}`       | Data only           |

**Supported path patterns:**
- `product/{productId}` - Product detail pages
- `category/{categoryId}` - Category pages
- `landing-page/{landingPageId}` - Landing pages

**Example requests:**
- `/store-api/content/product/abc123def456` - Renders product with ID abc123def456
- `/store-api/content/category/xyz789abc012` - Renders category with ID xyz789abc012
- `/store-api/content/landing-page/ghi345jkl678` - Renders landing page with ID ghi345jkl678
- `/store-api/content/product/abc123def456?elementId=product-images` - Renders only the `product-images` element subtree
- `/store-api/content-decomposed/product/abc123def456?elementId=product-images` - Same, decomposed format

**Database tables:**
- `product_content_layout` - Product layout assignments
- `category_content_layout` - Category layout assignments
- `landing_page_content_layout` - Landing page layout assignments

## Assignment Structure

```json
{
  "id": "<uuid>",
  "productId": "<product-uuid>",
  "salesChannelId": "<sales-channel-uuid>|null",
  "contentLayoutId": "<layout-uuid>"
}
```

Fields:
- Entity ID (`productId`/`categoryId`/`landingPageId`) - Entity to render
- `salesChannelId` - Sales channel scope (`null` = global)
- `contentLayoutId` - Layout to use

## Sales Channel Resolution

Resolution priority: **sales channel specific** > **global** (null `salesChannelId`).

Example: Product with global layout and B2B-specific layout. B2B channel uses specific assignment, all other channels use global.

## Default Layout

Products and categories can have a default layout, used for every entity of that type without an assignment. It is stored in system config (`core.content_system.default_product_content_layout` / `core.content_system.default_category_content_layout`, the keys `getDefaultContentLayoutConfigKey()` returns), so a sales channel value overrides the global one. Resolution order: assignment (sales channel, then global), then default (sales channel, then global). When neither exists, the route throws `CONTENT_SYSTEM__LAYOUT_ASSIGNMENT_NOT_FOUND` and the Storefront renders the CMS layout as before. `Validation/ContentLayoutDefaultValidator` guards the value and blocks deleting a default layout.
