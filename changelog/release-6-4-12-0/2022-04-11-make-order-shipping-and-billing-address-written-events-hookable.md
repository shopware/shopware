---
title: Make Order shipping- and billing adress EntityWrittenEvents hookable
issue: NEXT-21040
---
# Core
* Added `order_address` to the list of hookable entities, thus allowing that apps can subscribe to written and deleted webhooks for those entities.
