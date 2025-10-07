---
title: Fix admin search failure when Commercial is disabled and user has saved Commercial-only fields
issue: NEXT-00000
flag: 
author: Sarika / AI Pair
author_email: 
author_github: 
---
# Administration
* Admin global search no longer fails with FRAMEWORK__UNMAPPED_FIELD after disabling Commercial when a user has saved search preferences containing Commercial-only fields like `order.returnNumber`. Unknown fields are now ignored at runtime.

___
# Upgrade Information
No action required. Existing user preferences remain unchanged; invalid fields are skipped during query building.


