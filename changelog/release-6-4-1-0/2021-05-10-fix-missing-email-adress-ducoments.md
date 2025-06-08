---
title: Add company email address to documents
issue: NEXT-14870
author: Ramona Schwering
author_email: r.schwering@shopware.com 
author_github: leichteckig
---
# Core
* Changed placement of `document_side_info_phone_number` in `Framework/Resources/views/documents/invoice.html.twig`: 
  * Removed `document_side_info_phone_number` from `invoice.html.twig`. This is non-breaking because of the `includes` of this template.
  * Added `document_side_info_phone_number` to document header (`letter_header.html.twig`) instead
* Added `document_side_info_email` to document header (`letter_header.html.twig`)
