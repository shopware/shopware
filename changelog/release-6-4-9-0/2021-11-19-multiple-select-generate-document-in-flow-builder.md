---
title: Multiple select generate document in flow builder.
issue: NEXT-17914
---
# Core
* Changed `Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction` to be able to create single document and multiple documents
* Added migration `\Shopware\Core\Migration\V6_4\Migration1636362839FlowBuilderGenerateMultipleDoc`
___
# Administration
* Changed `sw-flow-generate-document-modal` to be able to select multiple document types
___
# Upgrade Information

## Allow generating multiple document types at backend
* Changed `Shopware\Core\Content\Flow\Dispatching\Action\GenerateDocumentAction` to be able to create single document and multiple documents

## Allow selecting multiple document types at generating document action in the flow builder.
* We are able to select multiple document types in a generated document action in the flow builder.
* The flow builder is to be able to show the action with the configuration data as a single document or multiple documents.
* the configuration schema payload in the flow builder for this action will change:

Before:
```json
"config": {
  "documentType": "credit_note",
  "documentRangerType": "document_credit_note"
},
```

After:
```json
"config": {
  "documentTypes": [
    {
      "documentType": "credit_note",
      "documentRangerType": "document_credit_note"
    },
    {
      "documentType": "delivery_note",
      "documentRangerType": "document_delivery_note"
    }
  ]
},
```
