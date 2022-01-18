---
title: Check selector for better dom-selector in plugin registration
issue: NEXT-19653
author: tinect
author_email: s.koenig@tinect.de
author_github: tinect
---
# Storefront
* Added private method `_queryElements` to `plugin.manager` to determ the best dom-selector based on common characters `a-zA-Z1-9_-`
* Added handling of HTMLCollection to `iterator.helper`

