---
title: Fix salutation in order document invoice not correct
issue: NEXT-23531
---
# Core
* Changed block `document_recipient` in `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` to replace `letterName` to `displayName` in `salutation`.
