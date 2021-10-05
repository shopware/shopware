---
title: Allow removal of .js-search-toggle-btn without exception in storefront
issue: NEXT-17332
author: Joshua Behrens
author_email: codde@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Changed `SearchWidgetPlugin` in `src/Storefront/Resources/app/storefront/src/plugin/header/search-widget.plugin.js` to disable autofocus on mobile, when `options.searchWidgetCollapseButtonSelector` can't be found instead of crashing.
