---
title: Improve twig performance
issue: NEXT-23137
author: Stefan Sluiter
author_email: s.sluiter@shopware.com
author_github: ssltg
---
# Core
* Changed function `sw_get_attribute` to more often direct call property getters from `Struct`
