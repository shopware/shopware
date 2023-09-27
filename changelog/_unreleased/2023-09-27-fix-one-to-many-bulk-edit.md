---
title: Fix Bulk Edit one to many associations length evaluation and infinite requests
issue: -
author: Lily Berkow
author_email: melanityt@gmail.com
author_github: TheAnimeGuru
---
# Administration
* Added `mappedExistAssociationsLen` which counts all arrays within `mappedExistAssociations` at each key entry
* Changed if for the recursion to not use `Object.keys(mappedExistAssociations).length` but the nested accounted length from `mappedExistAssociationsLen`
