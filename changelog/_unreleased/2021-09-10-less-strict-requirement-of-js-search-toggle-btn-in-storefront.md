---
title: Allow removal of .js-search-toggle-btn without exception in storefront
author: Joshua Behrens
author_email: codde@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Changed `SearchWidgetPlugin` in `src/Storefront/Resources/app/storefront/src/plugin/header/search-widget.plugin.js` to allow reference to the `.js-search-toggle-btn` is missing so block `layout_header_search_toggle` in `src/Storefront/Resources/views/storefront/layout/header/header.html.twig` can be emptied
