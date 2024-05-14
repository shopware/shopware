---
title: Add soft deletes to custom entities
issue: NEXT-31210
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Added new column `deleted_at` to the `custom_entity` table in order to not wipe out custom entity tables on app uninstall
