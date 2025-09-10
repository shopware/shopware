# Content System API & Consumer Architecture

## Executive Summary

The Content System V2 API provides a unified, flexible response structure that supports multiple rendering strategies. Consumers have complete control over how content is rendered, choosing between template-based, recursive, or hybrid approaches based on their specific needs and capabilities.

## Table of Contents

1. [API Response Structure](#api-response-structure)
2. [Architecture Concepts](#architecture-concepts)
3. [Architecture Decisions](#architecture-decisions)
4. [Type Categories](#type-categories)
5. [Slot Model Architecture](#slot-model-architecture)
6. [Consumer Rendering Modes](#consumer-rendering-modes)
7. [Component Mapping Strategy](#component-mapping-strategy)
8. [Implementation Examples](#implementation-examples)

## API Response Structure

### Unified Element Format

```typescript
interface Element {
    id: string;
    type: string;                // Immutable type identifier
    component: string;           // Namespaced component identifier (mutable)
    properties?: object;          // All configuration (merged data + style)
    data?: object | null;         // Runtime/hydrated data
    lazyLoad?: boolean;          // Deferred loading flag
    slots?: {                    // All content areas
        [key: string]: Element[];
    };
}
```

### Complete Response Structure

```typescript
interface ContentResponse {
    storeTemplate?: Template;     // Optional store frame
    pageTemplate: Template;       // Required page content
    context: ContextInfo;         // Request context
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
    data?: object;              // Template-level runtime data
    slots: {                    // Root content slots
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

## Architecture Decisions

### ADR-004: Component Removal from API

**Status**: Accepted  
**Context**: Component identifiers in the API created tight coupling between data layer and rendering layer, preventing independent evolution of frontend technologies  
**Decision**: Remove all component references from API responses, use only type identifiers  
**Consequences**:
- ✅ Complete decoupling of data and presentation
- ✅ Each consumer platform can use appropriate technology
- ✅ Frontend teams can refactor without API changes
- ✅ Simpler API with single responsibility
- ⚠️ Consumers must maintain type-to-component mappings
- ⚠️ No rendering hints from backend

**Alternatives Considered**:
1. Keep components in API (rejected: tight coupling)
2. Dual type/component system (rejected: complexity without benefit)
3. Optional component hints (rejected: still creates coupling)

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

**Characteristics:**
- Simple implementation
- No template knowledge required
- Renders everything in order
- Best for: Mobile apps, debugging, simple displays

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

**Characteristics:**
- Precise layout control
- Template decides slot usage
- Requires template registry
- Best for: Branded experiences, complex layouts

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

**Characteristics:**
- Best of both worlds
- Progressive enhancement
- Graceful degradation
- Best for: Mixed content, extensible systems

## Component Mapping Strategy

### Consumer-Side Organization Freedom

Each consumer can organize components however makes sense for their architecture:

```javascript
// Web Storefront - Organized by Business Domain
class WebStorefrontMapper {
    constructor() {
        this.componentMap = {
            // Product domain
            'product-box': 'Product.Card',
            'product-price': 'Product.Price',
            'product-image': 'Product.Image',
            'product-rating': 'Product.Rating',
            
            // Category domain
            'category-header': 'Category.Hero',
            'category-navigation': 'Category.Nav',
            
            // Cart/Checkout domain
            'cart-button': 'Cart.AddButton',
            'cart-summary': 'Cart.Summary',
            
            // Base components
            'heading': 'Base.Heading',
            'text': 'Base.Text',
            'button': 'Base.Button',
            'grid': 'Layout.Grid'
        };
    }
    
    getComponent(type) {
        return this.componentMap[type] || 'Base.Unknown';
    }
}

// Mobile App - Flat Component Structure
class MobileAppMapper {
    constructor() {
        this.componentMap = {
            // Simple flat mapping
            'grid': 'View',
            'section': 'ScrollView',
            'heading': 'Text',
            'text': 'Text',
            'button': 'TouchableOpacity',
            'image': 'FastImage',
            'product-box': 'ProductListItem',
            'product-price': 'PriceText',
            'category-header': 'CategoryBanner',
            'filter-panel': 'FilterBottomSheet',
            'cart-button': 'CartActionButton'
        };
    }
}

// Email Generator - HTML Elements
class EmailTemplateMapper {
    constructor() {
        this.componentMap = {
            // Direct HTML mapping
            'grid': 'table',
            'section': 'div',
            'heading': 'h2',
            'text': 'p',
            'button': 'a',
            'image': 'img',
            'product-box': 'tr',
            'category-header': 'div'
        };
    }
    
    getClass(type) {
        // CSS classes based on type
        return type.replace('-', '_');
    }
}
```

### Fallback Strategies

```javascript
class SmartComponentMapper {
    constructor(customMap = {}) {
        // Default mappings
        this.defaultMap = {
            'heading': 'h2',
            'text': 'p',
            'button': 'button',
            'image': 'img'
        };
        
        // Merge custom mappings
        this.componentMap = { ...this.defaultMap, ...customMap };
    }
    
    getComponent(type) {
        // Try exact match
        if (this.componentMap[type]) {
            return this.componentMap[type];
        }
        
        // Try pattern matching
        if (type.endsWith('-box')) return 'GenericBox';
        if (type.endsWith('-panel')) return 'GenericPanel';
        if (type.startsWith('heading-')) return 'Heading';
        
        // Fallback
        return 'UnknownElement';
    }
}
```

## Implementation Examples

### Example 1: Mobile App Consumer

Simple recursive rendering for React Native:

```javascript
class ContentRenderer {
    render(element) {
        const Component = this.getComponent(element.component);
        
        // Always recurse through all slots
        const children = Object.entries(element.slots || {})
            .flatMap(([_, content]) => 
                content.map(child => this.render(child))
            );
        
        return (
            <Component {...element.properties}>
                {children}
            </Component>
        );
    }
    
    getComponent(type) {
        // Simple component mapping
        switch(type) {
            case 'Sw:Static:Text': return Text;
            case 'Sw:Static:Image': return Image;
            default: return View;
        }
    }
}
```

### Example 2: Web Storefront Consumer

Template-based rendering with Vue.js:

```javascript
class StorefrontRenderer {
    constructor() {
        this.templates = new Map();
        this.registerTemplates();
    }
    
    render(element) {
        const template = this.templates.get(element.component);
        
        if (template) {
            // Use registered template
            return template({
                props: element.properties,
                data: element.data,
                slots: this.processSlots(element.slots)
            });
        }
        
        // Fallback to recursive
        return this.renderDefault(element);
    }
    
    processSlots(slots) {
        // Convert slots to rendered content
        const processed = {};
        for (const [name, content] of Object.entries(slots || {})) {
            if (name !== '_default') {  // Skip _default in template mode
                processed[name] = content.map(c => this.render(c));
            }
        }
        return processed;
    }
    
    renderDefault(element) {
        // Recursive fallback for unknown components
        const children = element.slots?._default || [];
        return children.map(child => this.render(child)).join('');
    }
}
```

### Example 3: Admin Preview Consumer

Debug mode showing all content:

```javascript
class PreviewRenderer {
    render(element, depth = 0) {
        const indent = '  '.repeat(depth);
        let html = `${indent}<div class="preview-element" 
                         data-component="${element.component}">\n`;
        
        // Show component info
        html += `${indent}  <div class="component-header">
            ${element.component}
        </div>\n`;
        
        // Render ALL slots (both named and _default)
        if (element.slots) {
            for (const [slotName, content] of Object.entries(element.slots)) {
                html += `${indent}  <div class="slot" data-slot="${slotName}">\n`;
                html += `${indent}    <span class="slot-name">${slotName}:</span>\n`;
                
                for (const child of content) {
                    html += this.render(child, depth + 2);
                }
                
                html += `${indent}  </div>\n`;
            }
        }
        
        html += `${indent}</div>\n`;
        return html;
    }
}
```

## Consumer Selection Strategy

Different consumers for different contexts:

| Consumer Type | Rendering Mode | Use Case |
|--------------|---------------|----------|
| Mobile App | Recursive | Simple, performant display |
| Web Storefront | Template | Rich, branded experience |
| Admin UI | Hybrid | Flexible editing interface |
| Email Generator | Template | Consistent formatting |
| Search Indexer | Recursive | Extract all content |
| Debug Tool | All slots | Development inspection |

## Key Principles

1. **API Agnostic**: The API doesn't dictate rendering strategy
2. **Complete Data**: All rendering modes get full data
3. **Consumer Choice**: Each consumer decides approach
4. **Progressive Enhancement**: Start simple, add complexity
5. **Graceful Degradation**: Unknown types still render
6. **Flexibility First**: Support any rendering strategy

## Benefits of Type-Only API

Removing components from the API enables:
- **Clean Separation**: Data independent of UI frameworks
- **Future-Proof**: New technologies don't require API changes  
- **Multi-Platform**: Each platform uses appropriate components
- **Team Autonomy**: Frontend teams refactor freely
- **Simplicity**: API focused only on data

The API provides **what** (types and data), consumers decide **how** (components and rendering).

## Migration Path

For existing consumers:

```javascript
// Old format
if (element.elements) {
    // Process elements array
}

// New format
if (element.slots?._default) {
    // Process _default slot (same content)
}

// Or use a compatibility layer
function getChildren(element) {
    return element.elements || element.slots?._default || [];
}
```

## Performance Considerations

- **Recursive**: Fastest for simple rendering
- **Template**: Overhead of template lookup/compilation
- **Hybrid**: Balance based on known components
- **Caching**: Cache rendered templates, not recursive output
- **Lazy Loading**: Respect `lazyLoad` flag in all modes

## Extensibility

Consumers can extend the system without modifying the API:

1. **Register new templates** for known components
2. **Add custom renderers** for specific categories
3. **Override slot handling** for special cases
4. **Implement custom fallbacks** for unknown components
5. **Layer additional processing** (animations, transitions)

This architecture ensures maximum flexibility while maintaining a clean, consistent API structure.