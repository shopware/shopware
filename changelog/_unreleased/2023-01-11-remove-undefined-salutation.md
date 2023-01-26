---
title: Remove undefined salutation
issue: NEXT-21203
---
# Core
* Added `Migration1673420896RemoveUndefinedSalutation` migration to remove `undefined` salutation and set `on delete` behaviour to `set null` for all foreign keys to the salutation table.
