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

## Header/Footer Layout Resolution

Headers and footers use domain-aware resolution with dedicated specification factories:

**Database tables:**
- `header_content_layout` - Header → layout assignments
- `footer_content_layout` - Footer → layout assignments

Each table contains:
- Domain ID (null = any domain)
- Sales channel ID (null = global)
- Layout ID (content_layout)

DomainAwareLayoutResolver queries with priority: domain + sales channel → sales channel → global.

## Key Classes

**Entity Factories:**
- `ProductSpecificationSource` - Creates RenderingSpecification for product paths
- `CategorySpecificationSource` - Creates RenderingSpecification for category paths
- `LandingPageSpecificationSource` - Creates RenderingSpecification for landing page paths
- `EntityLayoutResolver` (FactoryHelper/) - Shared layout resolution and placeholder processing
- `EntityLayoutContextFactory` (FactoryHelper/) - Shared entity-to-specification transformation

**Header/Footer Factories:**
- `HeaderSpecificationSource` - Creates RenderingSpecification for header layouts
- `FooterSpecificationSource` - Creates RenderingSpecification for footer layouts
- `DomainAwareLayoutResolver` (FactoryHelper/) - Domain-aware layout resolution
- `NavigationAliasResolver` (FactoryHelper/) - Resolves navigation aliases to category IDs

## Implementation

Factories extend AbstractSpecificationSource. ContentRoute tries factories in DI priority order (Chain of Responsibility). Entity factories check path prefix, query layout assignment, return RenderingSpecification or null.

## Subdirectories

- FactoryHelper/: Shared logic for entity layout resolution, domain-aware resolution, and navigation alias resolution
- Entity/: Entity definition interfaces and implementations for content layout assignable entities (including header/footer assignments)
