---
title: Fix select field issues
issue: NEXT-12800
author: Jannis Leifeld
author_email: j.leifeld@shopware.com 
author_github: @jleifeld
---
# Administration
* Changed scroll event listener to `sw-select-result-list__content` in `sw-select-result-list` to fix pagination event when user scrolls to the end in the select result list
* Removed the behavior that the selected entry is at the beginning of the computed value `visibleResults` in `sw-single-select` and `results` in `sw-entity-single-select`
* Fixed that the `sw-highlight-text` only appears on non selected elements in `sw-single-select` and `sw-entity-single-select`
