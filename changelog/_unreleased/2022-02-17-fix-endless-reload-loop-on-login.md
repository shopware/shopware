---
title: Fix administration reloading endlessly when using multiple domains on same main domain
issue: NEXT-18964
---
# Administration
* Added functionality to clear all cookies that are stored for all domains upwards from the current domain (i.e. x.example.com can clear example.com) to fix cookie-storage not removing a cookie that should be removed.
