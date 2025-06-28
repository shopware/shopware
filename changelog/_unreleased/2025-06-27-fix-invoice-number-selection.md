---
title: Fix invoice number selection for credit notes and storno invoices
author: Justus Geramb
author_email: justus@devite.io
author_github: @jgeramb
---
# Administration
* Allows the selection of invoice numbers of ZUGFeRD and embedded ZUGFeRD invoices for the creation of credit notes and storno invoices.

# Core
* Sets the custom config field 'invoiceNumber' for the document to the document number, so that the invoice number can be selected in the frontend.
