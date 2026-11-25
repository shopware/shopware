# Language Loader (`source: "language"`)

Loads available languages for the current sales channel.

```json
{
  "id": "language-switcher",
  "component": "Sw:LanguageSwitcher",
  "dataRequirements": {
    "languages": {
      "source": "language",
      "config": {
        "associations": ["locale"]
      }
    }
  }
}
```

Config fields:
- `associations` (optional) - Additional associations to load
