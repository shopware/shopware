---
title: Fix shipping costs price still visible when disabled display prices in documents
issue: NEXT-26159
---
# Core
* Changed `src/Core/Framework/Resources/views/documents/includes/shipping_costs.html.twig` to add condition with config `displayPrices`
