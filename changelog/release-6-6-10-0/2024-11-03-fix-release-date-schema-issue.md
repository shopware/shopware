---
title: Fix issue with defaulting release date to current day in product schema
issue: NEXT-38490
author: Stefan Pilz
author_email: shopware@stefanpilz.ltd
author_github: @StefanPilzLtd
---

# Storefront
* Added a conditional check in `buy-widget.html.twig` to ensure the release date only appears if explicitly set.
* Changed the schema from defaulting to the current date when no release date is provided.
