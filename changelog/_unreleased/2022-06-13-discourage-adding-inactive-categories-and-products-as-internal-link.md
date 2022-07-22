---
title: Discourage adding inactive categories and products as internal-link  
issue: NEXT-20784
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Administration
* Added slot `preview` to `sw-select-result` to have a slot before the text for easier object placement and similar code structure to table cells
* Added slot `result-label-preview` to `sw-entity-multi-select` to expose the `preview` slot from `sw-select-result` like the `result-label-property` slot
* Added active state icon to `sw-sales-channel-defaults-select` as preview for the result item depending on new property `shouldShowActiveState` (defaults to false)
* Added the `getActiveIconColor` method to `sw-entity-single-select/index.js`
* Changed `sw-text-editor-link-menu/index.ts`to align link parsing with link creation
* Changed the slot `actions` in `sw-tree-item.html.twig` to a `sw-vnode-renderer` in order to align it with the other extension points
