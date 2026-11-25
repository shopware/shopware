# Service Menu Loader (`source: "service_menu"`)

Loads the service navigation of the current sales channel — the secondary menu holding pages such as help or contact.

```json
{
  "id": "footer-service-menu",
  "component": "Sw:ServiceMenu",
  "dataRequirements": {
    "serviceMenu": {
      "source": "service_menu",
      "config": {
        "rootId": "service-navigation"
      }
    }
  }
}
```

Config fields:
- `rootId` (optional) - Navigation root the menu is read from. Defaults to `"service-navigation"`
