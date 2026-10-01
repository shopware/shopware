# Product Configurator Loader (`source: "product_configurator"`)

Loads the variant property groups of a configurable product — the option sets a visitor picks from on a variant product page.

```json
{
  "id": "variant-configurator",
  "component": "Sw:Product:Configurator",
  "properties": {
    "productId": "{{productId}}"
  },
  "dataRequirements": {
    "configurator": {
      "source": "product_configurator",
      "config": {
        "productId": "productId"
      }
    }
  }
}
```

Config fields:
- `productId` (optional) - Property on this element holding the product ID. Defaults to the property name `"productId"`
