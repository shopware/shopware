---
title: Fix app lifecycle issue with custom entity associations in case the referenced table does not exist
issue: 13273
---
# Core
* Changed behavior creating associations; an exception will be thrown if the referenced table does not exist 
* Added a new optional attribute named `ignore-missing-reference` with association types (`one-to-one`, `one-to-many`, `many-to-one`, `many-to-many`) to allow the schema updater to skip creating associations if the referenced table does not exist, improving flexibility and robustness during schema updates.

Example usage:
```xml
<one-to-many name="custom_entity" reference="quote_comment" ignore-missing-reference="true" store-api-aware="false" on-delete="set-null" />
```