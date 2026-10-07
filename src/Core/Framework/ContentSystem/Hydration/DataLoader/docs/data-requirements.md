# Data Requirements

How an element declares the data it needs, when to declare it, and the fields each declaration carries.

Data requirements specify what data needs loading for an element:

- What to load (entity, product listing, etc.)
- Where to store it (property key)
- How to load it (filters, sorting, associations)

The system loads this data automatically before rendering.

## Usage Guidelines

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

## Data Requirement Fields

Each data requirement is an object with these fields:

- `key` (optional) - Names this data requirement. It does NOT place the loaded value: the property the data lands under is always the entry's key in the `dataRequirements` map. `Layout/Codec/StoredElementCodec::decodeDataRequirements()` decodes by that map key and `Rendering/RenderedElementFactory::create()` mints from it, so an inner `key` differing from its map key changes nothing about placement. Omitted, it falls back to the map key.
- `source` (required) - Loader identifier (e.g., `"entity"`, `"entity_collection"`, `"product_listing"`, `"navigation"`)
- `config` (optional) - Loader-specific configuration object

> [!NOTE]
> A missing `key` is unambiguous rather than malformed, so decode fills it from the map key instead of failing. `Layout/Element/DataRequirement/DataRequirement::jsonSerialize()` always emits `key`, so such a requirement — written by raw SQL, or in a row predating that guarantee — gains an explicit `key` the next time its layout is saved: the stored bytes change, the tree does not.

## Multiple Data Requirements

Elements can declare multiple data requirements:

```json
{
  "id": "complex-page",
  "component": "Sw:Page:Complex",
  "properties": {
    "product": "{{productId}}",
    "relatedProductIds": ["{{relatedId1}}", "{{relatedId2}}", "{{relatedId3}}"]
  },
  "dataRequirements": {
    "mainProduct": {
      "source": "entity",
      "config": {
        "entity": "product",
        "property": "product"
      }
    },
    "relatedProducts": {
      "source": "entity_collection",
      "config": {
        "entity": "product",
        "property": "relatedProductIds"
      }
    },
    "navigation": {
      "source": "navigation",
      "config": {
        "rootId": "main-navigation",
        "depth": 2
      }
    }
  }
}
```
