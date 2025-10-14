# Content System - User Guide

This document is a configuration guide for shop operators and layout designers. It explains how to use the Content System through declarative JSON configurations. The guide covers routes, layouts, content elements, data loading, and context sharing.

## Table of Contents

1. [Overview](#overview)
2. [Content Elements](#content-elements)
3. [Routing](#routing)
4. [Data Loading](#data-loading)
5. [Context System](#context-system)
6. [Example: Product Detail Page](#example-product-detail-page)

## Overview

The Content System enables dynamic, data-driven layouts for your shop. Core capabilities include:

- Dynamic routing with URL patterns that resolve to entities
- Reusable layout templates with nested content elements
- Declarative data loading from the shop system
- Context sharing between parent and child elements
- Sales channel-specific layout assignments

### Core Concepts

**Content Elements** - Building blocks of layouts. Each element has a type (e.g., `Sw:Product:Card`), properties for configuration, slots for child elements, and optional data requirements.

**Placeholders** - Dynamic values in properties using `{{key}}` syntax. For example, `{{productId}}` gets replaced with the actual product UUID from the URL.

**Slots** - Named containers within elements. Each slot can hold multiple child elements.

**Data Requirements** - Declarations of what data an element needs. The system loads this data automatically before rendering.

**Context** - Mechanism for parent elements to share data with descendants. Providers expose data, consumers receive it without explicit passing through intermediate elements.

**Routes** - URL patterns stored in the database that map URLs to layouts. Merchants create these through the admin UI, not code.

### Data Flow

```mermaid
graph LR
    A["URL:<br/>/product/SW10234"] --> B["Route Matching:<br/>Pattern match"]
    B --> C["Parameter Resolution:<br/>Resolve to UUID"]

    classDef routing fill:#e1f5ff,stroke:#01579b,stroke-width:2px
    class A,B,C routing
```
```mermaid
graph LR
    D["Layout Resolution:<br/>Static or cascade"] --> E["Placeholder Replacement:<br/>{{product_id}}"]

    classDef layout fill:#fff9e1,stroke:#f57f17,stroke-width:2px
    class D,E layout
```
```mermaid
graph LR
    F["Data Loading:<br/>Load entities"] --> G["Context Distribution:<br/>Share data"]
    G --> H["Rendering:<br/>Display layout"]

    classDef hydration fill:#e8f5e9,stroke:#2e7d32,stroke-width:2px
    class F,G,H hydration
```

## Content Elements

### Structure

Each content element follows this structure:

```json
{
  "id": "product-card",
  "type": "Sw:Product:Card",
  "properties": {
    "text": "Featured Product",
    "productId": "{{product_id}}"
  },
  "slots": {
    "content": [
      {
        "id": "product-title",
        "type": "Sw:Product:Title"
      }
    ]
  },
  "data_requirements": {},
  "provides_context": {},
  "accepts_context": {}
}
```

Placeholders from routes (like `{{product_id}}`) must be assigned to properties before data loaders can use them:

```json
"properties": {
  "product": "{{product_id}}"
},
"data_requirements": {
  "product": {
    "config": {
      "property": "product"
    }
  }
}
```

**Required fields:**
- `id` - Unique identifier for the element (required for processing)
- `type` - Element type identifier

**Optional fields:**
- `properties` - Configuration values (static or placeholders)
- `slots` - Named containers with arrays of child elements
- `data_requirements` - Data loading declarations
- `provides_context` - Data shared with descendant elements
- `accepts_context` - Data received from ancestor elements

### Slots

Slots hold arrays of elements.

```json
{
  "id": "main-container",
  "type": "Sw:Grid",
  "properties": {
    "columns": "1"
  },
  "slots": {
    "header": [
      {"id": "logo", "type": "Sw:Content:Image"},
      {"id": "navigation", "type": "Sw:Navigation"},
      {"id": "search", "type": "Sw:Search"}
    ],
    "main": [
      {"id": "product-listing", "type": "Sw:Product:Listing"}
    ],
    "sidebar": [
      {"id": "filter-panel", "type": "Sw:Filter:Panel"},
      {"id": "promo-banner", "type": "Sw:Content:Image"}
    ]
  }
}
```

In this example, the `header` slot contains 3 elements, while `main` has 1 and `sidebar` has 2.

### Nested Containers

Containers can be nested for complex layouts:

```json
{
  "id": "page-layout",
  "type": "Sw:Grid",
  "properties": {
    "cssClass": "page-wrapper",
    "columns": "1"
  },
  "slots": {
    "default": [
      {
        "id": "hero-section",
        "type": "Sw:Grid",
        "properties": {
          "cssClass": "hero",
          "columns": "1"
        },
        "slots": {
          "default": [
            {
              "id": "heading",
              "type": "Sw:Content:Text",
              "properties": {
                "text": "Summer Sale",
                "style": "heading"
              }
            },
            {
              "id": "cta-button",
              "type": "Sw:Content:Button",
              "properties": {
                "label": "Shop Now"
              }
            }
          ]
        }
      }
    ]
  }
}
```

## Routing

Routes map URLs to content layouts using database-stored patterns.

### URL Patterns

Routes use Symfony-style URL patterns with placeholders:

```json
[
  "/product/{productNumber}",
  "/category/{slug}",
  "/category/{slug}/page/{page}",
  "/product/{productNumber}/review/{reviewId}"
]
```

Placeholders extract values from URLs and either:
1. Resolve entities from the database
2. Pass values directly to the layout

### Parameter Binding

Parameter binding defines how URL parameters are processed.

#### Resolution Parameters

Resolution parameters query the database to find entity IDs.

```json
{
  "id": "0199b95201fe70ec851e971788686601",
  "name": "Product Detail Route",
  "urlPattern": "/product/{productNumber}",
  "parameterBindings": {
    "productNumber": {
      "placeholder": "product_id",
      "resolution": {
        "entity": "product",
        "match_field": "productNumber",
        "constraints": {
          "active": true
        }
      }
    }
  },
  "layoutId": null,
  "layoutCascade": [
    {"entity": "product"},
    {"entity": "category", "via": "categories"},
    {"entity": null}
  ],
  "priority": 100,
  "active": true,
  "salesChannels": [
    {"id": "{{salesChannelId}}"}
  ]
}
```

Fields:
- `urlPattern` - URL pattern with placeholders (camelCase)
- `parameterBindings` - Parameter configuration (camelCase)
- `placeholder` - Name used in layouts (e.g., `{{product_id}}`)
- `resolution` - Entity lookup configuration
- `match_field` - Entity field to query
- `constraints` - Additional filters (e.g., only active products)
- `layoutId` - Static layout UUID or null
- `layoutCascade` - Dynamic lookup array or null
- `priority` - Route matching priority (higher checked first)
- `active` - Whether route is enabled
- `salesChannels` - Array of sales channel objects

Process:
1. URL: `/product/SW10234`
2. Extract parameter: `productNumber` = "SW10234"
3. Query database: `SELECT id FROM product WHERE productNumber = 'SW10234' AND active = true`
4. Result: `product_id` = "019456789abc..."
5. Placeholder `{{product_id}}` available in layout

#### Passthrough Parameters

Passthrough parameters use the URL value directly without database lookup. No `resolution` field means passthrough.

```json
{
  "id": "0199b95202fe70ec851e971788686602",
  "name": "Category Listing Route",
  "urlPattern": "/listing/{categoryId}",
  "parameterBindings": {
    "categoryId": {
      "placeholder": "category_id"
    }
  },
  "layoutId": "0199b95105fe70ec851e971788686505",
  "layoutCascade": null,
  "priority": 50,
  "active": true,
  "salesChannels": [
    {"id": "{{salesChannelId}}"}
  ]
}
```

Process:
1. URL: `/listing/electronics`
2. Extract parameter: `categoryId` = "electronics"
3. Placeholder `{{category_id}}` = "electronics" (no database query)

### Layout Assignment

Layout assignment determines which content layout a route uses. Both `layoutId` and `layoutCascade` must be present—set the unused one to `null`.

#### Static Assignment

Route uses a fixed layout. Set `layoutId` to UUID, `layoutCascade` to `null`:

```json
{
  "id": "0199b95202fe70ec851e971788686602",
  "name": "Category Listing Route",
  "urlPattern": "/listing/{categoryId}",
  "parameterBindings": {
    "categoryId": {"placeholder": "category_id"}
  },
  "layoutId": "0199b95105fe70ec851e971788686505",
  "layoutCascade": null,
  "priority": 50,
  "active": true,
  "salesChannels": [{"id": "{{salesChannelId}}"}]
}
```

Use when all URLs for this route use the same layout (e.g., contact page, terms page).

#### Dynamic Assignment (Cascade)

Route looks up layout based on entity and sales channel. Set `layoutId` to `null`, `layoutCascade` to array. Steps execute in order, first match wins.

```json
{
  "id": "0199b95201fe70ec851e971788686601",
  "name": "Product Detail Route",
  "urlPattern": "/product/{productNumber}",
  "parameterBindings": {
    "productNumber": {
      "placeholder": "product_id",
      "resolution": {
        "entity": "product",
        "match_field": "productNumber"
      }
    }
  },
  "layoutId": null,
  "layoutCascade": [
    {"entity": "product"},
    {"entity": "category", "via": "categories"},
    {"entity": null}
  ],
  "priority": 100,
  "active": true,
  "salesChannels": [{"id": "{{salesChannelId}}"}]
}
```

Cascade steps:
1. `{"entity": "product"}` - Direct entity match (layout assigned to resolved product ID)
2. `{"entity": "category", "via": "categories"}` - Association fallback (inherits from product's categories)
3. `{"entity": null}` - Default fallback (guaranteed fallback for sales channel)

Fields:
- `entity` - Entity type to match (or `null` for default)
- `via` - Association path for fallback lookups

Order rule: Most specific first, default last (specific → associations → default).

### Cascade Assignment Lookup

Cascade steps query the `content_layout_assignment` table. Assignments define which layout to use for which entity/sales channel combination.

Assignment structure:
```json
{
  "id": "<uuid>",
  "entityType": "product|category|null",
  "entityId": "<entity-uuid>|null",
  "salesChannelId": "<sales-channel-uuid>",
  "layoutId": "<layout-uuid>"
}
```

Example assignments (database records):
```json
[
  {
    "id": "0199b95301fe70ec851e971788686701",
    "entityType": null,
    "entityId": null,
    "salesChannelId": "{{salesChannelId}}",
    "layoutId": "0199b95101fe70ec851e971788686501"
  },
  {
    "id": "0199b95302fe70ec851e971788686702",
    "entityType": "category",
    "entityId": "0199b95509187207b86f6d01e3cc4f4c",
    "salesChannelId": "{{salesChannelId}}",
    "layoutId": "0199b95104fe70ec851e971788686504"
  },
  {
    "id": "0199b95303fe70ec851e971788686703",
    "entityType": "product",
    "entityId": "0199b95009fe70ec851e971788586445",
    "salesChannelId": "{{salesChannelId}}",
    "layoutId": "0199b95103fe70ec851e971788686503"
  }
]
```

Query logic:
- `{"entity": "product"}` → `WHERE entityType='product' AND entityId=<resolved_product_id> AND salesChannelId=<context>`
- `{"entity": "category", "via": "categories"}` → Load product.categories, `WHERE entityType='category' AND entityId IN <category_ids>`
- `{"entity": null}` → `WHERE entityType IS NULL AND entityId IS NULL AND salesChannelId=<context>`

First match wins, returns `layoutId` from matched assignment.

Example: Route resolves product `0199b95009fe70ec851e971788586445`
1. Step 1 queries product assignments → Finds assignment #3 → Returns layout `0199b95103fe70ec851e971788686503`
2. Steps 2-3 never execute (first match wins)

### Combined Example: Category with Pagination

Route combining resolution and passthrough parameters:

```json
{
  "id": "0199b95203fe70ec851e971788686603",
  "name": "Category with Pagination Route",
  "urlPattern": "/category/{slug}/page/{page}",
  "parameterBindings": {
    "slug": {
      "placeholder": "category_id",
      "resolution": {
        "entity": "category",
        "match_field": "slug"
      }
    },
    "page": {
      "placeholder": "page"
    }
  },
  "layoutId": null,
  "layoutCascade": [
    {"entity": "category"},
    {"entity": null}
  ],
  "priority": 75,
  "active": true,
  "salesChannels": [
    {"id": "{{salesChannelId}}"}
  ]
}
```

URL: `/category/electronics/page/2`
Result: `category_id` = UUID (from database), `page` = "2" (direct value)
Placeholders available: `{{category_id}}`, `{{page}}`

### Common Entity/Field Pairs

- Product: `"entity": "product"`, `"match_field": "seoPathInfo"` or `"productNumber"`
- Category: `"entity": "category"`, `"match_field": "slug"` or `"name"`

## Data Loading

### Data Requirements

Data requirements specify what data needs loading for an element:

- What to load (entity, product listing, etc.)
- Where to store it (property key)
- How to load it (filters, sorting, associations)

The system loads this data automatically before rendering.

### Usage Guidelines

Use data requirements when:
- Element needs data from the database
- Element displays dynamic content based on entities
- Element needs filtered or sorted lists

Don't use data requirements when:
- Element only displays static content
- Data comes via context from parent
- Element only uses configuration properties

> [!TIP]
> Place data requirements on the element that uses them. Only move them to a parent when multiple children need the same data. This keeps sub-tree extraction efficient and makes layouts more reusable.

### Available Loaders

#### Entity Loader (`source: "entity"`)

Loads a single entity by ID or property reference.

```json
{
  "id": "product-detail",
  "type": "Sw:Product:Detail",
  "data_requirements": {
    "product": {
      "key": "product",
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product",
        "associations": ["manufacturer", "cover", "categories"]
      }
    }
  }
}
```

Fields:
- `key` - Identifies this data requirement (must match object key)
- `source` - Loader identifier: "entity", "entity_collection", or "product_listing"
- `config` - Loader-specific configuration object
- `config.entity` - Entity name to load
- `config.property` - Property on this element containing the entity ID. The loader reads the ID from here. Defaults to entity name.
- `config.associations` - List of associations to load with the entity

After loading, access via element's `product` property → ProductEntity

A product card that loads its own data:

```json
{
  "id": "product-card",
  "type": "Sw:Product:Card",
  "properties": {
    "product": "{{product_id}}"
  },
  "data_requirements": {
    "product": {
      "key": "product",
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product"
      }
    }
  }
}
```

The `property` field points to the element's own property. The loader reads the ID from there, loads the product, and stores the result back in the same property.

If multiple elements need the same product, load it once at their parent and use the context system instead (see below).

#### Entity Collection Loader (`source: "entity_collection"`)

Loads multiple entities by their IDs.

```json
{
  "id": "product-slider",
  "type": "Sw:Product:Slider",
  "data_requirements": {
    "products": {
      "key": "products",
      "source": "entity_collection",
      "config": {
        "entity": "product",
        "ids": ["019456789abc", "019456789def", "019456789ghi"],
        "associations": ["cover"]
      }
    }
  }
}
```

After loading, access via element's `products` property → ProductCollection

#### Product Listing Loader (`source: "product_listing"`)

Loads product listings with filters, sorting, and pagination.

```json
{
  "id": "category-listing",
  "type": "Sw:Product:Listing",
  "properties": {
    "categoryId": "{{category_id}}",
    "limit": 24
  },
  "data_requirements": {
    "listing": {
      "key": "listing",
      "source": "product_listing",
      "config": {
        "page": "{{page}}"
      }
    }
  }
}
```

After loading, access via element's `listing` property → ProductListingResult

### Multiple Data Requirements

Elements can declare multiple data requirements:

```json
{
  "id": "complex-page",
  "type": "Sw:Page:Complex",
  "data_requirements": {
    "mainProduct": {
      "key": "mainProduct",
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product"
      }
    },
    "relatedProducts": {
      "key": "relatedProducts",
      "source": "entity_collection",
      "config": {
        "entity": "product",
        "ids": ["{{relatedId1}}", "{{relatedId2}}", "{{relatedId3}}"]
      }
    },
    "manufacturer": {
      "key": "manufacturer",
      "source": "entity",
      "config": {
        "entity": "manufacturer",
        "property": "manufacturer"
      }
    }
  }
}
```

## Context System

Use context when multiple elements need the same data. Load it once at the parent, share it with all children.

Example: A product page with title, price, and images all showing the same product. Load it once at the page level instead of three separate loads.

### Provider Configuration

Provider exposes data as context for descendants using `provides_context`.

```json
{
  "id": "product-detail-provider",
  "type": "Sw:Product:Detail",
  "data_requirements": {
    "product": {
      "key": "product",
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product"
      }
    }
  },
  "provides_context": {
    "product": {
      "type": "single",
      "distribution": "broadcast"
    }
  }
}
```

Fields:
- Context key (`"product"`) - Name consumers use to reference this context
- `type` - Context data type:
  - `"single"` - Single entity/value
  - `"collection"` - Array of entities/values
- `distribution` - How data is distributed to direct children:
  - `"broadcast"` - All children receive same data
  - `"indexed"` - Children receive data by position
  - `"keyed"` - Children receive data by their `data_key` property
  - `"sliced"` - Data split into chunks for each child
  - `"iterator"` - Round-robin distribution

Note: The context key in `provides_context` typically matches a property name loaded by `data_requirements`.

### Consumer Configuration

Consumer receives context from ancestor provider using `accepts_context`.

```json
{
  "id": "product-title-consumer",
  "type": "Sw:Product:Title",
  "accepts_context": {
    "product": {
      "type": "single",
      "required": true
    }
  }
}
```

Fields:
- Context key (`"product"`) - Must match provider's context key exactly
- `type` - Expected context data type:
  - `"single"` - Expects single entity/value
  - `"collection"` - Expects array of entities/values
- `required` - Whether context is mandatory:
  - `true` - Element fails if context unavailable
  - `false` - Element works without context

Consumer receives context data directly as a property.

### Distribution Strategies

Strategy determines how provider data is distributed to direct children.

**Broadcast** - All direct children receive identical data
Use case: Product detail page where all children display the same product

**Indexed** - Children receive data based on position. Child at index N gets data[N]
Use case: Top 3 products in specific slots

**Keyed** - Children receive data based on their `data_key` property matching keys in provider's data
Use case: Different product types in different sections (featured, sale, new arrivals)
Consumer needs: `"properties": {"data_key": "featured"}`

**Sliced** - Provider data split into chunks, each child gets a chunk
Use case: 12 products displayed across 3 columns (4 products per column)

**Iterator** - Distributes items round-robin across children
Use case: 10 products across 3 slots with even distribution

### Context Flow Rules

Context flows from ancestors to descendants, never sideways or upward.

```
✓ Valid:
  Provider (product detail)
    └─ Consumer (title)          ← Can receive

✗ Invalid:
  Consumer (title)
    └─ Provider (product detail) ← Cannot receive from child

✗ Invalid:
  Provider (product 1)
  Consumer (title)               ← Siblings cannot share
```

Distribution strategy applies only to direct children. Deeper descendants always receive like broadcast.

Practical implication: Place consumers as direct children of provider for strategies to work as intended.

### Context Example

Provider distributing context to multiple consumer children:

```json
{
  "id": "product-detail-context",
  "type": "Sw:Product:Detail",
  "data_requirements": {
    "product": {
      "key": "product",
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product"
      }
    }
  },
  "provides_context": {
    "product": {
      "type": "single",
      "distribution": "broadcast"
    }
  },
  "slots": {
    "default": [
      {
        "id": "product-title",
        "type": "Sw:Product:Title",
        "accepts_context": {
          "product": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "product-price",
        "type": "Sw:Product:Price",
        "accepts_context": {
          "product": {
            "type": "single",
            "required": true
          }
        }
      },
      {
        "id": "product-images",
        "type": "Sw:Product:Images",
        "accepts_context": {
          "product": {
            "type": "single",
            "required": true
          }
        }
      }
    ]
  }
}
```

Process:
1. Provider loads product via `data_requirements`
2. Provider exposes product as `"single"` context with `"broadcast"` distribution
3. All three children (`title`, `price`, `images`) receive the same product data
4. Each consumer declares context as `required: true`

## Example: Product Detail Page

Combined example showing routing, data loading, and context distribution.

**Route Configuration:**
```json
{
  "id": "0199b95201fe70ec851e971788686601",
  "name": "Product Detail Route",
  "urlPattern": "/product/{productNumber}",
  "parameterBindings": {
    "productNumber": {
      "placeholder": "product_id",
      "resolution": {
        "entity": "product",
        "match_field": "productNumber",
        "constraints": {
          "active": true
        }
      }
    }
  },
  "layoutId": null,
  "layoutCascade": [
    {"entity": "product"},
    {"entity": "category", "via": "categories"},
    {"entity": null}
  ],
  "priority": 100,
  "active": true,
  "salesChannels": [
    {"id": "{{salesChannelId}}"}
  ]
}
```

**Layout Configuration:**
```json
{
  "id": "product-detail-page",
  "type": "Sw:Grid",
  "properties": {
    "product": "{{product_id}}",
    "columns": "1",
    "gap": 32
  },
  "data_requirements": {
    "product": {
      "key": "product",
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product",
        "associations": ["cover", "manufacturer", "categories"]
      }
    }
  },
  "provides_context": {
    "product": {
      "type": "single",
      "distribution": "broadcast"
    }
  },
  "slots": {
    "default": [
      {
        "id": "breadcrumb",
        "type": "Sw:Navigation:Breadcrumb",
        "properties": {"showHome": true}
      },
      {
        "id": "product-images",
        "type": "Sw:Product:Images",
        "accepts_context": {
          "product": {"type": "single", "required": true}
        }
      },
      {
        "id": "product-info",
        "type": "Sw:Grid",
        "properties": {"columns": "1", "gap": 16},
        "accepts_context": {
          "product": {"type": "single", "required": false}
        },
        "slots": {
          "default": [
            {
              "id": "product-title",
              "type": "Sw:Product:Title",
              "accepts_context": {
                "product": {"type": "single", "required": true}
              }
            },
            {
              "id": "product-price",
              "type": "Sw:Product:Price",
              "accepts_context": {
                "product": {"type": "single", "required": true}
              }
            },
            {
              "id": "product-description",
              "type": "Sw:Product:Description",
              "accepts_context": {
                "product": {"type": "single", "required": true}
              }
            }
          ]
        }
      },
      {
        "id": "add-to-cart",
        "type": "Sw:Product:AddToCart",
        "accepts_context": {
          "product": {"type": "single", "required": true}
        }
      }
    ]
  }
}
```
