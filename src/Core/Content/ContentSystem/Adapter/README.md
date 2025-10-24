# Adapter

Connects CMS-capable entities (Product, Category, Landing Page) to ContentSystem rendering. Enables entities to use content layouts without URL routing infrastructure.

## CMS-Capable Entities

Products, Categories, and Landing Pages can have ContentSystem layouts assigned directly in database. Layouts specific to sales channel or global across all channels.

**Supported entities:**
- **Product** - Path pattern: `product/{productId}` (e.g., `/store-api/content/product/abc123`)
- **Category** - Path pattern: `category/{categoryId}` (e.g., `/store-api/content/category/xyz789`)
- **Landing Page** - Path pattern: `landing-page/{landingPageId}` (e.g., `/store-api/content/landing-page/def456`)

Each entity type has dedicated factory creating RenderingSpecification from entity ID.

## Entity-to-Layout Resolution

Entities map to layouts via database tables with sales channel fallback:

**Database tables:**
- `product_content_layout` - Product → layout assignments
- `category_content_layout` - Category → layout assignments
- `landing_page_content_layout` - Landing page → layout assignments

Each table contains:
- Entity ID (product/category/landing_page)
- Layout ID (content_layout)
- Sales channel ID (null = global)

LayoutSearchHelper queries with priority: sales channel specific → global.

## Key Classes

- `ProductContextFactory` - Creates RenderingSpecification for product paths
- `CategoryContextFactory` - Creates RenderingSpecification for category paths
- `LandingPageContextFactory` - Creates RenderingSpecification for landing page paths
- `LayoutSearchHelper` - Shared query logic with sales channel fallback

## Implementation

Factories implement RenderingSpecificationFactoryInterface. ContentRoute tries factories in DI priority order (Chain of Responsibility). Entity factories check path prefix, query layout assignment, return RenderingSpecification or null.

RouteBasedContextFactory (Routing/) handles all non-entity paths as catch-all.

## Subdirectories

None currently. All classes in Adapter/ root.
