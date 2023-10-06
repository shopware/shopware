---
title: Fix UI is broken in documents when unchecked display line items.
issue: NEXT-22394
---
# Core
* Changed `src/Core/Framework/Resources/views/documents/includes/position.html.twig` to add condition `config.displayLineItems`.
* Changed `src/Core/Framework/Resources/views/documents/includes/shipping_costs.html.twig` to add condition `config.displayLineItems`.
