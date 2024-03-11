---
title: Change display product properties case
issue: NEXT-30315
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
---
# Core
* Removed display bug when searching for nonexistent product properties in `sw-product-properties`
* Added value `searchTerm` to `sw-simple-search-field` in `sw-product-add-properties-modal` so that the `sw-simple-search-field` isn't cleared after entering a search
