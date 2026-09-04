# Currency Loader (`source: "currency"`)

Loads available currencies for the current sales channel.

```json
{
  "id": "currency-switcher",
  "component": "Sw:CurrencySwitcher",
  "dataRequirements": {
    "currencies": {
      "source": "currency",
      "config": {
        "associations": []
      }
    }
  }
}
```

Config fields:
- `associations` (optional) - Additional associations to load
