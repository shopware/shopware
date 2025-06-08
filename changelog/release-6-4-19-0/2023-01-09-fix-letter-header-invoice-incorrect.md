---
title: Fix letter header invoice incorrect
issue: NEXT-23740
---
# Core
* Changed block `document_recipient_sender` in `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` to revert company address.
