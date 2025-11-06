---
title: fix app lifecycle with custom entity associations
issue: 13273
---
# Core
* Added a new optional attribute, `ignore-missing-reference` for custom entity field associations in the schema definition. This allows the schema updater to skip creating associations if the referenced table does not exist, improving flexibility and robustness during schema updates.

Example usage:
```xml
<one-to-many name="custom_entity" reference="quote_comment" ignore-missing-reference="true" store-api-aware="false" on-delete="set-null" />
```