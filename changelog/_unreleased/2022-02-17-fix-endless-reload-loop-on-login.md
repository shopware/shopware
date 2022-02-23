---
title: Fix administration reloading endlessly when using multiple domains on same main domain
issue: NEXT-18964
---
# Administration
* Changed usage of cookie-storage npm package. Cookies will now only be set for the current domain (because no domain is specified)
