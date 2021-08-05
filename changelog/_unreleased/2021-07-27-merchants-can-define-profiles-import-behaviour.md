---
title: Merchants can define profiles import behaviour
issue: NEXT-16186
flag: FEATURE_NEXT_8097
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Changed the import to use the `createEntities` and `updateEntities` config flags if set and call the corresponding repository method (create, update, upsert)
___
# Administration
* Added profile import settings section in the `sw-import-export-edit-profile-modal` component with two switch fields for `Create new entities` and `Update existing data` which set the corresponding config values.
___
