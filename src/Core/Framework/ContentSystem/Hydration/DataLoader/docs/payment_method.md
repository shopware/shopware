# Payment Method Loader (`source: "payment_method"`)

Loads available payment methods.

```json
{
  "id": "payment-methods",
  "component": "Sw:PaymentMethods",
  "dataRequirements": {
    "paymentMethods": {
      "source": "payment_method",
      "config": {
        "onlyAvailable": true,
        "associations": ["media"]
      }
    }
  }
}
```

Config fields:
- `onlyAvailable` (optional) - Only return available payment methods. Defaults to `true`.
- `associations` (optional) - Additional associations to load
