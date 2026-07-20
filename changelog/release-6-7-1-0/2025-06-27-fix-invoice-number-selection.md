---
title: Fix invoice number selection for credit notes and storno invoices
author: Justus Geramb
author_email: justus@devite.io
author_github: @jgeramb
---
# Administration
* Changed the filter for documents to allow the selection of invoice numbers from ZUGFeRD and embedded ZUGFeRD invoices when creating credit notes and storno invoices.
___
# Core
* Added the document number as the custom config field 'invoiceNumber' to the document, so that the invoice number can be selected in the frontend.
