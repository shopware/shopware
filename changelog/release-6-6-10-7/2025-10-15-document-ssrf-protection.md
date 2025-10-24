---
title: Ensure protection against ssrf attacts in documents
---

# Core

* Changed `Core/Framework/Resources/views/documents/includes/comment.html.twig`,
          `src/Core/Framework/Resources/views/documents/includes/footer.html.twig`   
          `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig`
          `src/Core/Framework/Resources/views/documents/includes/payment_shipping.html.twig`
          `src/Core/Framework/Resources/views/documents/includes/position.html.twig`
          `src/Core/Framework/Resources/views/documents/includes/position_header.html.twig`
          `src/Core/Framework/Resources/views/documents/includes/shipping_costs.html.twig`
          `src/Core/Framework/Resources/views/documents/includes/summary.html.twig`
          to protect against ssrf attacts.
