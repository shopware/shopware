# Shopware CMS API Response Structure Documentation

## Overview

The CMS v2 architecture uses a hierarchical component-based structure with two rendering strategies: granular (element-by-element) and template-based (optimized for known patterns).

## Core Principles

### 1. Hierarchical Component Structure

The response structure mirrors how developers naturally think about page composition. Consider the familiar pattern of any web application:

```
Store Template (Outer Frame)
├── Header Section
├── Page Content (Placeholder)
└── Footer Section

Page Template (Inner Content)
├── Elements
│   └── Slots
│       └── Elements (recursive)
│           └── Slots (can contain more elements)
```

Certain elements persist across navigation (header and footer) while others change with each page load. The Store Template acts as the persistent shell, while Page Templates slot into the content area.

### 2. Dual Rendering Approach

1. **Granular Rendering**: Platforms parse every element individually. Mobile apps translate each component into native UI elements. These renderers walk the tree element by element, building the interface piece by piece.

2. **Template-Based Rendering**: Renderers recognize high-level patterns. When encountering a product listing, they apply a pre-built template using the provided data rather than parsing every button and text field individually.

### 3. Version Control

Each element declares its version using semantic versioning (e.g., `2.1.0`). This version refers to the element type, not the data within it.

This enables:
- Backward compatibility without breaking existing implementations
- Progressive enhancement as new features roll out
- A/B testing different implementations of the same component
- Graceful degradation when renderers encounter newer element versions

## Response Structure

### Top Level Structure

Every API response starts with this foundation:

```json5
{
  "storeTemplate": { /* Global store frame */ },
  "pageTemplate": { /* Page-specific content */ },
  "context": { /* Request context */ },
  "apiVersion": "6.6.0.0",
  "timestamp": "2024-01-22T10:30:00.000+00:00"
}
```

The separation between `storeTemplate` and `pageTemplate` at the root level immediately signals the dual nature of the content. Renderers know from the first parse what remains static and what changes.

### Store Template

The Store Template defines your application's persistent frame:

```json5
{
  "id": "uuid",
  "type": "store-template",
  "version": "1.0.0",
  "name": "Template Name",
  "sections": {
    "header": { /* Header elements */ },
    "content": { "placeholder": "page-content" },
    "footer": { /* Footer elements */ }
  }
}
```

The `content` section contains only a placeholder. The Store Template declares where page content belongs without specifying what that content might be.

### Page Template

Page Templates contain the page-specific content:

```json5
{
  "id": "uuid",
  "type": "category-listing",
  "version": "2.1.0",
  "name": "Page Name",
  "data": { /* High-level page data */ },
  "style": { /* Page-level styling */ },
  "elements": [ /* Page elements */ ],
  "seo": { /* SEO metadata */ }
}
```

The `type` field here (`category-listing`) immediately tells smart renderers what kind of page they're dealing with. They can choose to use specialized rendering logic or fall back to element-by-element parsing.

## Element Structure

Every element adheres to a consistent pattern that balances flexibility with predictability:

```json5
{
  "id": "uuid",
  "type": "element-type",
  "version": "1.0.0",
  "data": { /* Element configuration and data */ },
  "style": { /* Visual styling properties */ },
  "slots": { /* Named slots for nested elements */ },
  "elements": [ /* Direct child elements */ ],
  "attributes": { /* HTML attributes (optional) */ }
}
```

**Note on Storage vs API Structure**: While the API presents `data`, `style`, and `attributes` as separate properties for clarity, these are all stored in a single `config` JSON field in the database. The response builder extracts and structures these during hydration.

### Key Properties

#### `id` (required)
Every element receives a UUID (`0188e8c5-5fc4-7b41-ac14-ccc21fd2aa13` format). This enables efficient caching strategies and provides a stable reference for debugging.

#### `type` (required)
The element's type (`product-box`, `heading`, `button`, `grid`) serves as the primary key for renderer selection. Simple types like `heading` might have straightforward renderers, while complex types like `product-box` could trigger specialized logic.

#### `version` (required)
Following semantic versioning (`MAJOR.MINOR.PATCH`), this field enables graceful evolution. A renderer built for `product-box` version `2.0.0` can still handle version `2.1.0` (new features) but might need fallback logic for version `3.0.0` (breaking changes).

#### `data` (optional)
This property adapts to each element's needs. A heading might only need `{ "text": "Hello", "level": 2 }`, while a product box could contain entire product entities with prices, descriptions, and inventory data.

#### `style` (optional)
Rather than embedding CSS or platform-specific styling, the API uses semantic style properties. Instead of `margin: 16px`, you'll see `spacing: "medium"`. This abstraction allows each platform to interpret styling according to its own design system.

#### `slots` (optional)
Named slots enable sophisticated composition patterns. A product box might define `image`, `content`, and `actions` slots, each filled with appropriate elements. This approach mirrors modern component frameworks while remaining platform-agnostic.

#### `elements` (optional)
For simpler sequential content, the `elements` array provides ordered children. The distinction between `slots` (named, semantic) and `elements` (ordered, sequential) gives developers precise control over content structure.

#### `attributes` (optional)
This escape hatch allows platform-specific metadata. Web renderers might use these for CSS classes or data attributes, while other platforms can safely ignore them.

## Style Properties

### Common Style Properties

```json5
{
  "style": {
    // Layout
    // layout: "horizontal" | "vertical" | "grid" | "sidebar-content"
    "layout": "horizontal",
    // alignment: "left" | "center" | "right" | "space-between"
    "alignment": "left",
    // verticalAlignment: "top" | "center" | "bottom"
    "verticalAlignment": "top",
    
    // Sizing
    // size: "small" | "medium" | "large" | "extra-large"
    "size": "small",
    // width: "small" | "medium" | "large" | "extra-large"
    "width": "150",
    // height: "40" | "full" | "auto"
    "height": "40",
    // maxWidth: "1200" | "full"
    "maxWidth": "1200",
    
    // Spacing
    // padding: "small" | "medium" | "large"
    "padding": "small",
    "spacing": {
      "top": "small",
      "bottom": "medium",
      "horizontal": "large"
    },
    // gap: "small" | "medium" | "large"  
    "gap": "small",
    
    // Visual
    // background: "white" | "light-gray" | "dark-blue" | "#hexcode"  
    "background": "white",
    // textColor: "dark" | "gray" | "white"  
    "textColor": "dark",
    // corners: "none" | "slightly-rounded" | "rounded" | "fully-rounded"  
    "corners": "none",
    // shadow: "none" | "subtle" | "small" | "medium" | "large
    "shadow": "none",
    
    // Typography
    // weight: "normal" | "medium" | "semibold" | "bold"
    "weight": "normal",
    // lineHeight: "tight" | "normal" | "relaxed"
    "lineHeight": "tight",
    "truncate": {
      "lines": 2,
      "showEllipsis": true
    },
    
    // Effects
    // hoverEffect: "none" | "lift" | "glow"
    "hoverEffect": "none",
    // clickable: true | false
    "clickable": true,
    // sticky: true | false
    "sticky": true,
    // stickyOffset: 100
    "stickyOffset": "100"
  }
}
```

These semantic values solve a fundamental problem: how do you express visual intent without dictating implementation? A mobile app interprets `"padding": "medium"` differently than a web renderer, but both understand the intent.

## Data Loading Patterns

The API supports two distinct data loading strategies, each optimized for different scenarios:

### 1. Synchronous (Embedded Data)

When performance allows and data size is reasonable, embedding complete data provides the best user experience:

```json5
{
  "type": "product-box",
  "data": {
    "product": {
      "id": "uuid",
      "name": "Product Name",
      "price": { /* price data */ }
      // ... complete product data
    }
  }
}
```

### 2. Asynchronous (Lazy Loading)

For larger datasets or below-the-fold content, lazy loading preserves initial load performance:

```json5
{
  "slots": {
    "content": {
      "referenceId": "product-content-002",
      "lazyLoad": true,
      "endpoint": "/api/cms/elements/product-content-002"
    }
  }
}
```

The choice between these patterns isn't just about performance—it's about user experience. Critical above-the-fold content typically uses synchronous loading, while supplementary content can load as needed.

## Element Type Examples

### Container Elements

**Grid**
```json5
{
  "type": "grid",
  "version": "2.0.0",
  "data": {
    "columns": {
      "desktop": 12,
      "tablet": 8,
      "mobile": 4
    },
    "responsive": true
  }
}
```

Grid elements demonstrate how responsive design translates across platforms. The column counts provide hints rather than rigid rules—a native mobile app might interpret these differently than a web renderer.

### Content Elements

**Heading**
```json5
{
  "type": "heading",
  "version": "1.0.0",
  "data": {
    "text": "Green Tea",
    "level": 1  // h1-h6
  }
}
```

Even simple elements benefit from explicit versioning. Future versions might add text alignment or styling options without breaking existing renderers.

**Button**
```json5
{
  "type": "button",
  "version": "1.0.0",
  "data": {
    "text": "Add to Cart",
    "action": "add-to-cart",
    "productId": "uuid"
  },
  "style": {
    "variant": "primary",
    "size": "medium",
    "fullWidth": true
  }
}
```

Buttons showcase the separation between data (what happens when clicked) and presentation (how it looks). The `action` field enables platform-specific handling—web renderers might trigger JavaScript, while mobile apps could invoke native functions.

### Complex Components

**Product Box**
```json5
{
  "type": "product-box",
  "version": "2.3.0",
  "data": {
    "type": "basic-box",
    "showBuyButton": true,
    "showWishlist": true,
    "product": { /* complete product entity */ }
  },
  "slots": {
    "image": { /* image element */ },
    "content": { /* product info */ },
    "actions": { /* buttons */ }
  }
}
```

Complex components leverage both high-level data and granular slots. Smart renderers can use the complete product data with optimized templates, while simple renderers can still construct the UI from individual slot elements.

## Context Object

Context provides the environmental information necessary for proper rendering:

```json5
{
  "context": {
    "salesChannelId": "uuid",
    "languageId": "uuid",
    "currencyId": "uuid",
    "customerId": "uuid",
    "customerGroupId": "uuid"
  }
}
```

Prices display in the correct currency, content appears in the right language, and customer-specific elements (like special pricing) render appropriately.

## Runtime SEO Generation

SEO metadata is generated dynamically at runtime based on the current context, loaded entities, and configured patterns. This ensures SEO always reflects the current state of content without redundant storage.

### SEO Pattern Configuration

Templates can define SEO generation patterns in their `data` field:

```json5
// In cms_v2_template.data or translation
{
  "seoPatterns": {
    "metaTitle": "{product.name} - {product.manufacturer.name} | {shop.name}",
    "metaDescription": "{product.metaDescription|default:product.description|truncate:160}",
    "canonicalUrl": "/{product.seoUrl}",
    "robots": "{product.robots|default:'index,follow'}",
    "ogTitle": "{product.name}",
    "ogDescription": "{product.teaser|truncate:200}",
    "ogImage": "{product.cover.media.url}"
  }
}
```

### Generation Process

1. **Entity Data**: Use SEO fields from the primary entity (product, category, etc.)
2. **Pattern Resolution**: Apply template patterns with entity data
3. **Context Enhancement**: Add shop name, language-specific content
4. **Fallback Chain**: Entity SEO → Template patterns → System defaults

### Response Structure

The generated SEO metadata appears in the API response:

```json5
{
  "seo": {
    "metaTitle": "Green Tea - Premium Teas | My Shop",
    "metaDescription": "Discover our premium green tea selection...",
    "canonicalUrl": "/products/green-tea",
    "robots": "index,follow",
    "ogTitle": "Green Tea",
    "ogDescription": "Premium green tea from the finest gardens...",
    "ogImage": "https://shop.com/media/products/green-tea.jpg"
  }
}
```

### Benefits

- **Always Current**: SEO updates automatically when entity data changes
- **No Redundancy**: Single source of truth in entity data
- **Context-Aware**: Adapts to language, sales channel, and user context
- **Flexible Patterns**: Easy to adjust SEO strategies without data migration
- **Fallback Support**: Graceful degradation when data is missing

Non-web platforms can ignore this data, while web renderers get optimized, context-aware SEO metadata without additional API calls.

## Best Practices

### 1. Element Granularity

Finding the right level of granularity requires judgment. Too granular, and you're sending unnecessary data. Too coarse, and you lose flexibility. The guideline: each element should represent a single logical unit of content with one clear purpose.

Composition happens through slots, not by cramming multiple responsibilities into one element. Sequential content uses the elements array. This separation keeps individual components simple while enabling complex layouts.

### 2. Data Organization

Data organization follows a principle of minimal repetition. High-level data lives in parent components, flowing down as needed. When multiple elements need the same data, place it at the lowest common ancestor rather than duplicating it.

Reference IDs enable data sharing without duplication. If ten elements need product data, they can reference a single source rather than each carrying their own copy.

### 3. Styling

The semantic style system requires discipline. Resist the temptation to add pixel-specific values or platform-specific properties. Each style property should express intent, not implementation.

Responsive behavior emerges from the combination of semantic styles and intelligent renderers. The API provides hints; renderers make platform-appropriate decisions.

### 4. Versioning

Semantic versioning isn't just a convention—it's a contract. MAJOR versions signal breaking changes requiring renderer updates. MINOR versions add capabilities while maintaining compatibility. PATCH versions fix bugs without changing behavior.

The key: version the element type, not the data structure. This enables element evolution without constantly breaking renderers.

### 5. Performance

Performance optimization happens at multiple levels. Lazy loading keeps initial payloads manageable. Component-level caching reduces redundant rendering. Shallow nesting minimizes parsing overhead.

The architecture encourages performance-conscious design without mandating specific optimizations. Each platform can implement caching and loading strategies that make sense for its constraints.

## Rendering Implementation Guide

### For Granular Renderers

Building a granular renderer follows a predictable pattern:

1. Start with recursive parsing—each element potentially contains more elements
2. Version checking happens first—can this renderer handle this element version?
3. Type-based renderer selection routes each element to appropriate logic
4. Style properties translate into platform-specific presentation
5. Child elements render in order, slots fill with their designated content

No element is special; the same logic handles everything from buttons to complex product listings.

### For Template-Based Renderers

Smart renderers take a different approach:

1. Type identification happens at a high level—is this a product listing? A content page?
2. Data extraction pulls complete entities from the data property
3. Pre-built templates consume this data efficiently
4. Style properties might select template variants rather than individual styles
5. Granular child elements become optional—the template handles internal structure

This approach trades flexibility for performance and consistency. When you know you're rendering a product grid, why parse individual elements when a optimized template exists?

## Extension Points

### Custom Elements

The system anticipates extension from day one. Custom elements need only follow the established patterns:

- Choose a unique type identifier (consider namespacing)
- Implement consistent versioning from the start
- Define a clear data structure
- Use compatible style properties

The same rendering logic that handles built-in elements automatically supports custom ones. No special registration or configuration required.

### Platform-Specific Rendering

Each platform brings unique capabilities and constraints. The architecture acknowledges this reality:

- Web renderers might add CSS classes from the attributes property
- Mobile apps could implement gesture handling for interactive elements
- Native applications might use platform-specific optimizations

The key: platform-specific enhancements should improve the experience without becoming requirements. A button should still be recognizable as a button, regardless of platform-specific features.

## Entity Architecture

The entity architecture and database schema for CMS v2 is documented separately to maintain clear separation between API structure and implementation details.

→ See [CMS v2 Entity Architecture](./shopware-cms-v2-entity-architecture.md)

This separate documentation includes:
- Database schema and table structures
- Entity definitions and relationships
- Extension table design patterns
- Hydration strategies
- Implementation examples with code
