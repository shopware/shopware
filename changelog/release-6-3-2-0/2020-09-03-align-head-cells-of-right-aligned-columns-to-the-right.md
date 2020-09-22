---
title:          Align head cells of right-aligned columns to the right
issue:          NEXT-10618
author:         Hannes Wernery
author_email:   hannes.wernery@viison.com
author_github:  @hanneswernery
---
# Administration
* Changed method `getHeaderCellClasses` ìn `sw-data-grid/index.js` to add a class of `sw-data-grid__cell--align-${column.align}` and add css for `.sw-data-grid__cell--align-right`
    
