---
title: Fix document type display blank if language is child
issue: NEXT-30087
---
# Administration
* Changed `createdComponent` method in `sw-order-select-document-type-modal` component to use `documentType.translated.name` instead of `documentType.name` to display the document type's name
