# Authoring Content Elements

The JSON shape of a content element as a layout author writes it: its fields, its slots, and how containers nest.

## Structure

Each content element follows this structure:

```json
{
  "id": "product-card",
  "component": "Sw:Product:Card",
  "properties": {
    "text": "Featured Product",
    "productId": "{{productId}}"
  },
  "slots": {
    "content": [
      {
        "id": "product-title",
        "component": "Sw:Product:Title"
      }
    ]
  }
}
```

Placeholders (like `{{productId}}`) must be assigned to properties before data loaders can use them:

```json
"properties": {
  "product": "{{productId}}"
},
"dataRequirements": {
  "product": {
    "source": "entity",
    "config": {
      "entity": "product",
      "property": "product"
    }
  }
}
```

**Required fields:**
- `id` - Unique identifier for the element (required for processing)
- `component` - Element component identifier

**Optional fields:**
- `properties` - Configuration values (static or placeholders)
- `slots` - Named containers with arrays of child elements
- `dataRequirements` - Data loading declarations
- `providesContext` - Data shared with descendant elements
- `acceptsContext` - Data received from ancestor elements, or from the layout's root-ambient context when the entry declares `scope: "root"`
- `attributedSpecifications` - System bookkeeping mapping a wired key to the binding specification that wired it (see [Binding/README.md](../../../Binding/README.md)). Re-derived on every save, so hand-editing it has no effect; never part of the Store API response.

## Slots

Slots hold arrays of elements.

```json
{
  "id": "main-container",
  "component": "Sw:Grid",
  "properties": {
    "columns": "1"
  },
  "slots": {
    "header": [
      {"id": "logo", "component": "Sw:Content:Image"},
      {"id": "navigation", "component": "Sw:Navigation"},
      {"id": "search", "component": "Sw:Search"}
    ],
    "main": [
      {"id": "product-listing", "component": "Sw:Product:Listing"}
    ],
    "sidebar": [
      {"id": "filter-panel", "component": "Sw:Filter:Panel"},
      {"id": "promo-banner", "component": "Sw:Content:Image"}
    ]
  }
}
```

In this example, the `header` slot contains 3 elements, while `main` has 1 and `sidebar` has 2.

## Nested Containers

Containers can be nested for complex layouts:

```json
{
  "id": "page-layout",
  "component": "Sw:Grid",
  "properties": {
    "cssClass": "page-wrapper",
    "columns": "1"
  },
  "slots": {
    "default": [
      {
        "id": "hero-section",
        "component": "Sw:Grid",
        "properties": {
          "cssClass": "hero",
          "columns": "1"
        },
        "slots": {
          "default": [
            {
              "id": "heading",
              "component": "Sw:Content:Text",
              "properties": {
                "text": "Summer Sale",
                "style": "heading"
              }
            },
            {
              "id": "cta-button",
              "component": "Sw:Content:Button",
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
