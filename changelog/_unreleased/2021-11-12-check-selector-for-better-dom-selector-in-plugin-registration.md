---
title: Check selector for better dom-selector in plugin registration
issue: 
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---
# Storefront
* Added private method `_queryElements` to `plugin.manager` to determ the best dom-selector based on common characters `a-zA-Z1-9_-`
* Changed private method `_initializePlugin` to use private method `queryElements` when selector is a string

