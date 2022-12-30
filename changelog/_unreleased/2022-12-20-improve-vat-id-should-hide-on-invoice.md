---
title: Improve customer VAT-ID in invoice document to hide or display
issue: NEXT-23483
---
# Administration
* Changed `src/module/sw-settings-document/page/sw-settings-document-detail/sw-settings-document-detail.html.twig` to add new block `sw_settings_document_detail_content_field_customer_vat_id`
___
# Core
* Changed block `document_recipient` in `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` to changed condition with customer vat-id.
