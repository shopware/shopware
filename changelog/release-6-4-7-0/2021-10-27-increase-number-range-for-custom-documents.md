---
title: Increase number range for custom documents
issue: NEXT-18145

 
---
# Core
* Added `getCollectionClass` to `NumberRangeSalesChannelDefinition` to return correct collection when hydrated
* Added `getEntityClass` to `NumberRangeSalesChannelDefinition` to return correct entity when hydrated
___
# Administration
* Added function `addAdditionalInformationToDocument` to `sw-order-document-settings-modal` to allow extending components to add additional data to the document before it is created
* Changed behaviour of `sw-order-document-settings-modal` to increase number range on `onCreateDocument`
* Changed `sw-order-document-settings-invoice-modal` to use `addAdditionalInformationToDocument` instead of overriding `onCreateDocument`
* Changed behaviour of `onCreateDocument` of `sw-order-document-card` to set `isLoadingDocument` to false after the document was created
