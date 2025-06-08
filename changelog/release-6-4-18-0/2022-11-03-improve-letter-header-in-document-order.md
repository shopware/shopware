---
title: Improve letter header in document order
issue: NEXT-23740
---
# Core
* Changed block `document_recipient` in `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` to revert company address.
* Changed block `document_sender_address` in `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` to deprecated block `document_side_company_name`.
