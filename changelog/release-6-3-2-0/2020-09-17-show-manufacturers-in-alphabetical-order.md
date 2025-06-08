---
title: Show manufacturers in alphabetical order
issue: NEXT-10671
---
# Storefront
* Added `sort((a, b) => a.translated.name|lower > b.translated.name|lower)` in `src/Storefront/Resources/views/storefront/component/listing/filter-panel.html.twig` to show manufacturers in alphabetical order
