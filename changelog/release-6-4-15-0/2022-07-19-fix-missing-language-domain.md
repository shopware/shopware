---
title: Fix missing language domain on context switch
issue: NEXT-22495
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Core
* Changed `ContextSwitchRoute` to return no redirect url if no domain with the switched language is found instead of a null pointer exception.
