---
title: Use order currency if defined to display line items, default to context currency
issue: NEXT-10868
author: Melvin Achterhuis & Fabian Blechschmidt
author_email: fabian@winkelwagen.de
author_github: Schrank
---

# Storefront
* Changed display of wrong currency for old orders, because the currency is always used from context instead of order
* Deprecated `displayMode` in `total-price.html-.twig`
