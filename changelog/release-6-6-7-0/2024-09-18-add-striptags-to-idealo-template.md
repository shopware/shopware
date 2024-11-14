---
title: Fixed the issue where product export feed does not meet idealo requirements
issue: NEXT-37658
---
# Administration
* Added `striptags` to filter strips tags for product description in `src/Administration/Resources/app/administration/src/module/sw-sales-channel/product-export-templates/idealo/body.csv.twig` to avoid HTML format.
