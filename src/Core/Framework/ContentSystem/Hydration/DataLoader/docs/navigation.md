# Navigation Loader (`source: "navigation"`)

Loads navigation tree data for menus.

```json
{
  "id": "main-nav",
  "component": "Sw:Navigation:Menu",
  "properties": {
    "activeId": "{{categoryId}}"
  },
  "dataRequirements": {
    "navigation": {
      "source": "navigation",
      "config": {
        "rootId": "main-navigation",
        "depth": 3,
        "activeProperty": "activeId"
      }
    }
  }
}
```

Config fields:
- `rootId` (optional) - Navigation root ID or alias. Defaults to `"main-navigation"`. Available aliases: `main-navigation`, `service-navigation`, `footer-navigation`.
- `depth` (optional) - Navigation tree depth. Defaults to `2`.
- `activeProperty` (optional) - Element property name to read the active category ID from. Defaults to `"activeId"`.

After loading, access via the requirement key (e.g., `navigation` property).
