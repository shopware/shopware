# ContentSystem

The ContentSystem provides a data-driven design and layout system for serving structured content through the storefront and Store API. It allows layouts to define element trees with data requirements and context distribution, which are rendered through an event-driven pipeline.

## Entity-Based Rendering

Products, Categories, and Landing Pages can have content layouts assigned directly in the database. These assignments support sales channel specificity, allowing different layouts per sales channel or a global fallback.

### Store API Endpoint

The content is served through the `/store-api/content/{path}` endpoint with the following supported path patterns:

- `product/{productId}` - Renders product content using `ProductSpecificationSource`
- `category/{categoryId}` - Renders category content using `CategorySpecificationSource`
- `landing-page/{landingPageId}` - Renders landing page content using `LandingPageSpecificationSource`

For detailed request and response schemas, see the OpenAPI specification files in `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/`. The complete Store API schema is also available at runtime via `/store-api/_info/openapi3.json`.

## Header/Footer Rendering

Headers and Footers use dedicated endpoints with domain-aware resolution. Unlike entity-based rendering, these are singletons per domain/sales channel combination.

### Store API Endpoints

Header content is served through `/store-api/content-header*` endpoints:
- `/store-api/content-header` - Full format
- `/store-api/content-header-decomposed` - Decomposed format
- `/store-api/content-header-skeleton` - Skeleton format
- `/store-api/content-header-data` - Data format

Footer content is served through `/store-api/content-footer*` endpoints with the same format variants.

### Domain-Aware Resolution

Header/footer layouts use a three-tier resolution with domain specificity:

1. Domain + SalesChannel specific assignment
2. SalesChannel specific assignment (domain = null)
3. Global assignment (both = null)

This differs from entity-based resolution which uses only sales channel fallback.

### Rendering Pipeline

The rendering process follows these steps:

1. **Layout Resolution** - The system resolves the entity ID and sales channel to find the appropriate content layout assignment.
2. **Load** - The layout ID is used to load the `ContentLayoutEntity` from the database.
3. **PreHydration Events** - Subscribers prepare the layout by resolving placeholders and applying virtual root wrapping.
4. **Hydration** - The layout and its data requirements are processed to produce a populated content tree.
5. **PostHydration Events** - Subscribers finalize the layout by cleaning up virtual roots and extracting partial content.

## Data Flow

The content flows through the system as follows:

1. An entity ID is passed to a context factory.
2. The factory creates a `RenderingSpecification` containing the layout ID and placeholders.
3. The `LayoutLoader` retrieves the `ContentLayoutEntity` from the repository.
4. The `PreContentHydrationEvent` is dispatched for layout preparation.
5. The `ContentElementHydrator` loads data and resolves context.
6. The `PostHydrationEvent` is dispatched for finalization.
7. The response is returned to the client.

Entity-specific factories query assignment tables (`product_content_layout`, `category_content_layout`, `landing_page_content_layout`) with sales channel fallback. The system first checks for a sales channel-specific layout, then falls back to a global layout if none is found.

Header/footer factories query `header_content_layout` and `footer_content_layout` tables with domain-aware fallback. The system checks domain + sales channel first, then sales channel only, then global.

## Key Classes

The system is organized into entity factories and rendering pipeline components.

### Entity Factories

These factories translate entity IDs into rendering specifications:

- **ProductSpecificationSource** - Creates specifications for product paths (located in `Adapter/`)
- **CategorySpecificationSource** - Creates specifications for category paths (located in `Adapter/`)
- **LandingPageSpecificationSource** - Creates specifications for landing page paths (located in `Adapter/`)
- **EntityLayoutResolver** - Provides shared layout resolution logic (located in `Adapter/FactoryHelper/`)

### Header/Footer Factories

These factories create specifications for header/footer rendering with domain-aware resolution:

- **HeaderSpecificationSource** - Creates specifications for header layouts (located in `Adapter/`)
- **FooterSpecificationSource** - Creates specifications for footer layouts (located in `Adapter/`)
- **DomainAwareLayoutResolver** - Resolves layouts with domain → sales channel → global fallback (located in `Adapter/FactoryHelper/`)
- **NavigationAliasResolver** - Resolves navigation aliases to category IDs (located in `Adapter/FactoryHelper/`)

### Rendering Pipeline Components

These classes handle the core rendering process:

- **ContentSection** - Enum defining content sections (HEADER, FOOTER, MAIN) with route path segments and cache tag generation
- **RenderingSpecification** - Contains the complete rendering specification including layout ID, placeholders, request data, target element, and layout type
- **PlaceholderValues** - An immutable map of placeholder values used during rendering
- **LayoutLoader** - Loads `ContentLayoutEntity` instances from the repository (located in `Layout/Loader/`)
- **ContentElement** - Represents the tree structure with slots, data requirements, and context (located in `Layout/Element/`)
- **ContentElementHydrator** - Loads data and resolves context for elements (located in `Hydration/`)
- **ContentPipeline** - Orchestrates the rendering pipeline (located in module root)
- **RenderingSpecificationResolver** - Selects the appropriate factory using the Chain of Responsibility pattern (located in module root)
- **RenderingCacheContext** - Tracks cache state during rendering including tags and disabled flag (located in module root)
- **ContentRoute** - Provides Store API endpoints (located in `SalesChannel/`)

## Architecture

### Rendering Pipeline Design

The rendering pipeline is designed to be independent of the data source. Context factories extend `AbstractSpecificationSource` to translate entity IDs into `RenderingSpecification` objects. The pipeline receives a specification and renders the content without needing to know the original data source.

The current factories for Product, Category, and Landing Page entities enable the content system to serve as a replacement for existing storefront and Store API pages, including product detail pages, product listing pages, and category pages.

## Subdirectories

The module is organized into the following subdirectories:

- **Adapter/** - Contains entity-specific factories and layout assignment entities
- **Cache/** - HTTP cache integration and invalidation
- **Condition/** - Defines visibility conditions for content elements
- **Event/** - Contains event definitions for the hydration lifecycle
- **EventSubscriber/** - Implements pre and post hydration pipeline transformations
- **Helper/** - Provides utility classes
- **Hydration/** - Handles data loading and context distribution
- **Layout/** - Contains the element tree, components, entities, and field types
- **Output/** - Defines response formatting structs
- **SalesChannel/** - Implements Store API endpoints
