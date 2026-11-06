# Shipping Method Loader (`source: "shipping_method"`)

Loads available shipping methods.

```json
{
  "id": "shipping-methods",
  "component": "Sw:ShippingMethods",
  "dataRequirements": {
    "shippingMethods": {
      "source": "shipping_method",
      "config": {
        "onlyAvailable": true,
        "associations": ["media"]
      }
    }
  }
}
```

Config fields:
- `onlyAvailable` (optional) - Only return available shipping methods. Defaults to `true`.
- `associations` (optional) - Additional associations to load
