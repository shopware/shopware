# Content System API & Consumer Architecture

## Executive Summary

The Content System V2 API provides a unified, flexible response structure that supports multiple rendering strategies. Consumers have complete control over how content is rendered, choosing between template-based, recursive, or hybrid approaches based on their specific needs and capabilities.

## Key Principles

The content system architecture maintains strict separation between data and presentation. The API remains agnostic to rendering strategies, providing complete data regardless of the chosen rendering mode. Each consumer independently decides how to interpret and display content.

## Table of Contents

1. [API Response Structure](#api-response-structure)
2. [Architecture Concepts](#architecture-concepts)
3. [Type Categories](#type-categories)
4. [Slot Model Architecture](#slot-model-architecture)
5. [Consumer Rendering Modes](#consumer-rendering-modes)
6. [Component Mapping Strategy](#component-mapping-strategy)

## API Response Structure

### Unified Element Format

```typescript
interface Element {
    id: string;
    type: string;                // Immutable type identifier
    properties?: object;         // All configuration
    data?: object | null;        // Runtime/hydrated data
    lazyLoad?: boolean;          // Deferred loading flag
    slots?: {                    // All content areas
        [key: string]: Element[];
    };
}
```

### Complete Response Structure

```typescript
interface ContentResponse {
    storeTemplate?: Template;    // Optional store frame
    pageTemplate: Template;      // Required page content
    context: ContextInfo;        // Request context
    seo: SeoMetadata;            // SEO information
    apiVersion: string;
    timestamp: string;
}

interface Template {
    id: string;
    type: string;                // Template type identifier
    version: string;             // Template version (semantic)
    name: string;
    properties?: object;         // Template-level configuration
    data?: object;               // Template-level runtime data
    slots: {                     // Root content slots
        [key: string]: Element[];
    };
}
```

## Architecture Concepts

### Types

Types are simple string identifiers that appear in API responses (`product-box`, `heading`, `filter-panel`). They describe what an element represents semantically and remain stable to ensure data integrity. The API only knows about types.

### Categories

Categories are internal database classifications (`container`, `static`, `entity`, `service`) that determine hydration strategy. They control how and when data is loaded but are never exposed in API responses. Each type has exactly one category stored in the registry.

### Components

Components are rendering implementations chosen entirely by consumers. They can be React components, Vue components, HTML elements, or any other rendering technology. The mapping from types to components happens exclusively on the consumer side.

### Decision: Component Removal from API

**Context**: Component identifiers in the API created tight coupling between data layer and rendering layer, preventing independent evolution of frontend technologies. Each consumer platform (web, mobile, email) needed different component implementations but the API was forcing a single component model.  
**Decision**: Remove all component references from API responses, use only type identifiers. The API provides semantic types (what content represents), consumers map these to their own components (how to render).  

**Benefits Realized**:
- **Clean Separation**: Data layer completely independent of UI frameworks and rendering technologies
- **Future-Proof**: New frontend technologies can be adopted without any API changes required
- **Multi-Platform Support**: Each platform uses components appropriate to its technology stack (React Native for mobile, HTML for email, Vue for web)
- **Team Autonomy**: Frontend teams can refactor, replace, or upgrade their component libraries freely without backend coordination
- **API Simplicity**: Single responsibility - providing data and structure, not rendering instructions

**Consequences**:
- ✅ Complete decoupling of data and presentation layers
- ✅ Each consumer platform can use appropriate technology
- ✅ Frontend teams can refactor without API changes  
- ✅ Simpler API with single responsibility
- ✅ Enables progressive enhancement of frontend without backend changes
- ⚠️ Consumers must maintain type-to-component mappings
- ⚠️ No rendering hints from backend
- ⚠️ Each consumer must implement its own component library

**Alternatives Considered**:
1. Keep components in API (rejected: tight coupling, prevents platform-specific optimizations)
2. Dual type/component system (rejected: complexity without clear benefit, confusing separation)
3. Optional component hints (rejected: still creates coupling, ambiguous responsibility)
4. Platform-specific APIs (rejected: violates DRY principle, maintenance burden)

**Example Impact**: 
The API returns `type: "product-box"` rather than `component: "ProductCard"`. The web storefront maps this to a Vue component, the mobile app to a React Native component, and the email generator to an HTML table row. Each implementation is optimized for its platform without affecting others.

## Type Categories

The database stores a category for each type to control hydration:

| Category | Purpose | Hydration Phase | Caching |
|----------|---------|-----------------|---------|
| **container** | Layout structure | Pass-through | Full |
| **static** | Fixed content | Immediate (config) | Full |
| **entity** | Database content | Phase 1 (DAL) | Contextual |
| **service** | Dynamic content | Phase 3 (services) | Limited |

Types are simple identifiers:

```javascript
// Product-related types
"product-box"
"product-price"
"product-image"
"product-rating"

// Category-related types  
"category-header"
"category-navigation"

// Layout types
"grid"
"section"
"tabs"

// Content types
"heading"
"text"
"image"
"button"

// Commerce types
"cart-button"
"wishlist-button"
"filter-panel"
"sorting-dropdown"
```

The category (container/static/entity/service) is determined by the type's data requirements, not its name.

## Slot Model Architecture

### Dual-Purpose Slots

- **`_default` slot**: Natural content flow for recursive rendering
- **Named slots**: Template injection points for controlled rendering

```json
{
  "type": "product-box",
  "slots": {
    "_default": [
      
    ],
    "image": [],
    "price": [],
    "actions": []
  }
}
```

**Slot Semantics**: `_default` provides fallback content, named slots provide template hooks, both enable maximum flexibility.

## Consumer Rendering Modes

### Mode 1: Pure Recursive Rendering

Traverses entire content tree, rendering all slots:

```
FUNCTION renderRecursive(element):
    component = mapTypeToComponent(element.type)
    output = openTag(component, element.properties)
    
    FOR EACH slot IN element.slots:
        FOR EACH child IN slot:
            output += renderRecursive(child)
    
    output += closeTag(component)
    RETURN output
```

Simple implementation requiring no template knowledge. Renders all content in order, making it ideal for mobile apps, debugging, and straightforward displays.

### Mode 2: Pure Template Rendering

Uses registered templates for known components:

```
FUNCTION renderWithTemplate(element):
    component = mapTypeToComponent(element.type)
    template = getTemplate(component)
    
    IF template EXISTS:
        // Template controls which slots render and where
        RETURN template.render(
            properties: element.properties,
            data: element.data,
            slots: element.slots
        )
    ELSE:
        RETURN renderRecursive(element)
```

Provides precise layout control through templates that decide slot usage. Requires a template registry but enables branded experiences and complex layouts.

### Mode 3: Hybrid Approach

Combines both strategies adaptively:

```
FUNCTION renderHybrid(element):
    component = mapTypeToComponent(element.type)
    template = getTemplate(component)
    
    IF template EXISTS:
        RETURN template.render(element)
    ELSE IF element.slots._default EXISTS:
        output = ""
        FOR EACH child IN element.slots._default:
            output += renderHybrid(child)
        RETURN output
    ELSE:
        RETURN empty
```

Combines both strategies for progressive enhancement with graceful degradation. Ideal for mixed content and extensible systems.

## Component Mapping Strategy

### Consumer-Side Organization Freedom

Each consumer can organize components however makes sense for their architecture:

```
// Web Storefront - Shopware Component Mapping
MAPPER WebStorefrontMapper:
    DEFINE componentMap AS:
        // Grid and Layout
        'grid' → 'Sw:Grid:Container'
        'column' → 'Sw:Grid:Column'
        'section' → 'Sw:Layout:Section'
        
        // Content elements
        'heading' → 'Sw:Content:Heading'
        'text' → 'Sw:Content:Text'
        'button' → 'Sw:Content:Button'
        'html' → 'Sw:Content:Html'
        
        // Media elements
        'image' → 'Sw:Media:Image'
        'video' → 'Sw:Media:Video'
        'gallery' → 'Sw:Media:Gallery'
        
        // Product domain
        'product-box' → 'Sw:Product:Box'
        'product-price' → 'Sw:Product:Price'
        'product-image' → 'Sw:Product:Image'
        'product-rating' → 'Sw:Product:Rating'
        
        // Category domain
        'category-header' → 'Sw:Category:Header'
        'category-navigation' → 'Sw:Category:Navigation'
        
        // Commerce features
        'cart-button' → 'Sw:Commerce:CartButton'
        'wishlist-button' → 'Sw:Commerce:WishlistButton'
        'filter-panel' → 'Sw:Commerce:FilterPanel'
    
    FUNCTION getComponent(type):
        IF componentMap HAS type:
            RETURN componentMap[type]
        ELSE:
            RETURN 'Sw:Base:Unknown'

// Mobile App - Native Component Mapping
MAPPER MobileAppMapper:
    DEFINE componentMap AS:
        // Layout components
        'grid' → 'View'
        'section' → 'ScrollView'
        'column' → 'View'
        
        // Basic elements
        'heading' → 'Text'
        'text' → 'Text'
        'button' → 'TouchableOpacity'
        'image' → 'FastImage'
        
        // Product components
        'product-box' → 'ProductListItem'
        'product-price' → 'PriceText'
        'product-image' → 'ProductImage'
        'product-rating' → 'RatingStars'
        
        // Category components
        'category-header' → 'CategoryBanner'
        'category-navigation' → 'CategoryTabs'
        
        // Commerce components
        'filter-panel' → 'FilterBottomSheet'
        'cart-button' → 'CartActionButton'
        'wishlist-button' → 'WishlistButton'
    
    FUNCTION getComponent(type):
        IF componentMap HAS type:
            RETURN componentMap[type]
        ELSE:
            RETURN 'View'  // Default container

// Email Generator - HTML Element Mapping
MAPPER EmailTemplateMapper:
    DEFINE componentMap AS:
        // Layout elements
        'grid' → 'table'
        'column' → 'td'
        'section' → 'div'
        
        // Content elements
        'heading' → 'h2'
        'text' → 'p'
        'button' → 'a'
        'image' → 'img'
        'html' → 'div'
        
        // Product elements (table-based for email)
        'product-box' → 'tr'
        'product-price' → 'span'
        'product-image' → 'img'
        
        // Category elements
        'category-header' → 'div'
        'category-navigation' → 'table'
        
        // Commerce elements
        'cart-button' → 'a'
        'filter-panel' → 'div'
    
    FUNCTION getComponent(type):
        IF componentMap HAS type:
            RETURN componentMap[type]
        ELSE:
            RETURN 'div'  // Safe default
    
    FUNCTION getCssClass(type):
        RETURN REPLACE(type, '-', '_')  // Convert to CSS-safe class
```


## System Extensibility

The web storefront consumer is designed for extensibility without modifying core files. Extensions integrate through Twig templates, Symfony UX components, and Shopware's plugin system.

### Component Registration

Components are autodiscovered based on filesystem conventions. The system scans predefined directories and automatically maps types to templates:

```
// Autodiscovery paths (example)
AUTODISCOVERY_PATHS:
    Core:    'src/Storefront/Resources/views/components/cms/{type}.html.twig'
    Plugins: 'custom/plugins/*/src/Resources/views/components/cms/{type}.html.twig'
    Apps:    'custom/apps/*/Resources/views/components/cms/{type}.html.twig'
```

### Template System

Templates use a waterfall composition pattern where content flows downward through slots. Each template is isolated and cannot modify parent structures. The system enforces strict separation through several principles:

**Template Isolation**: Each component template renders only its own markup structure. Templates should not use Twig's `{% extends %}` or `{% block %}` statements to modify parent templates.

**Slot-Based Composition**: Components delegate rendering of child content through a `render_slot()` (not necessarily called like this) function. Slots act as injection points where child components render their content. The parent defines where slots appear but cannot modify what renders inside them.

**Unidirectional Data Flow**: Data and rendering control flow strictly downward through the component tree. A parent component passes data to its slots, which render child components, which may have their own slots for further nesting. This creates a clear hierarchy: parent → slot → child → slot → grandchild, with no ability for children to reach upward and modify ancestors.

### Decision: No Template Override System

**Context**: Many CMS and e-commerce platforms (including Shopware) allow templates to be overridden with extensions. This creates hidden complexity where merchants cannot see what has been modified, updates break customizations, and debugging becomes difficult when multiple extensions override the same template. The question arose whether the new content system should support template overrides for existing element types.  
**Decision**: Element type templates cannot be overridden. Each type maps to exactly one template. Extensions must create new element types rather than modifying existing ones.  
**Consequences**:
- ✅ Predictable behavior - "product-box" always renders the same way
- ✅ Explicit merchant choice - admin UI shows all available element types
- ✅ Stable updates - core changes don't break customizations
- ✅ Clear ownership - each element type has one responsible party
- ✅ Better performance - direct type-to-template mapping without cascade resolution
- ✅ Encourages innovation - developers create new experiences rather than patching
- ⚠️ More element types in the system (but provides clarity)
- ⚠️ No quick patches for core element bugs (must wait for updates or create alternatives)
- ⚠️ Developers must learn to extend through composition rather than modification

**Alternatives Considered**:
1. Priority-based override system (rejected: hidden complexity, debugging nightmares)
2. Explicit override registry (rejected: still creates unpredictability for merchants)
3. Theme-only overrides (rejected: doesn't solve the core problems)
4. Inheritance-based templates (rejected: violates composition principles)
