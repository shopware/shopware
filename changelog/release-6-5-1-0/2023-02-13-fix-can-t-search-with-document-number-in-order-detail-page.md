---
title: Fix can't search with document number in order detail page
issue: NEXT-24862
---
# Administration
* Changed `documentCriteria` computed property in `sw-order-document-card` component to add query with keys `config.documentNumber` and `config.documentDate`
