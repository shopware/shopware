---
title: Allow delete by filter over sync API
issue: NEXT-38713
---
# Core
* Changed `\Shopware\Core\Framework\Api\Sync\SyncService` to allow deletion of entities by filter for all entities and remove default api limits.
___
# Next Major Version Changes
## Deletes by filter over the Sync API
The sync API allows now to add a filter to the delete request to delete multiple entities at once. This is useful if you want to delete all entities that match a certain criteria:
```json
[
  {
    "action": "delete",
    "entity": "product",
    "payload": [],
    "filter": [
      {
        "field": "name",
        "type": "equals",
        "value": "test"
      }
    ]
  }
]
```