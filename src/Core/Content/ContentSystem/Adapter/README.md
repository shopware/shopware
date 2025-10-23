# Adapter

Connects CMS-capable entities (Product, Category) to ContentSystem rendering. Enables entities to use content layouts without URL routing infrastructure.

## CMS-Capable Entities

Products and Categories can have ContentSystem layouts assigned directly in database. Layouts specific to sales channel or global across all channels.

**Supported entities:**
- **Product** - Direct product detail page rendering via `/store-api/content/product/{productId}`
- **Category** - Direct category page rendering via `/store-api/content/category/{categoryId}`

Each entity type has dedicated factory creating RenderingSpecification from entity ID.

## Entity-to-Layout Resolution

Entities map to layouts via database tables with sales channel fallback:

**Database tables:**
- `product_content_layout` - Product → layout assignments
- `category_content_layout` - Category → layout assignments

Each table contains:
- Entity ID (product/category)
- Layout ID (content_layout)
- Sales channel ID (null = global)

LayoutSearchHelper queries with priority: sales channel specific → global.

## Key Classes

- `ProductContextFactory` - Creates RenderingSpecification for product paths
- `CategoryContextFactory` - Creates RenderingSpecification for category paths
- `LayoutSearchHelper` - Shared query logic with sales channel fallback

## Implementation

Factories implement RenderingSpecificationFactoryInterface. ContentRoute tries factories in DI priority order (Chain of Responsibility). Entity factories check path prefix, query layout assignment, return RenderingSpecification or null.

RouteBasedContextFactory (Routing/) handles all non-entity paths as catch-all.

## Subdirectories

None currently. All classes in Adapter/ root.
