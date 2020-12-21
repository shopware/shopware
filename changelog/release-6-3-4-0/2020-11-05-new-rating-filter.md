---
title: New rating filter in the storefront
issue: NEXT-9359
author: Timo Altholtmann
---
# Storefront
* Deprecated the storefront js plugin `filter-rating-plugin.js` for 6.4.0. The file will be removed and a new rating plugin will be introduced.
* Deprecated `src/Storefront/Resources/app/storefront/src/scss/component/_filter-rating.scss` for 6.4.0. The file will be removed.
* Deprecated `src/Storefront/Resources/views/storefront/component/listing/filter/filter-rating.html.twig` for 6.4.0. The file will be removed.
* Deprecated block `component_filter_panel_item_rating` in `src/Storefront/Resources/views/storefront/component/listing/filter-panel.html.twig`. The block will be removed and replaced with a new rating plugin.
