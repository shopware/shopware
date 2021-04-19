---
title: Add reset to default for excluded search term.
issue: NEXT-14417
---
# Administration
* Added new function `resetExcludedSearchTerm` in `Shopware\Administration\Controller` with route `/api/_action/search-keyword/reset-excluded-search-term` which reset excluded term value to default.
* Added method `onResetExcludedSearchTermDefault` in `sw-settings-search-excluded-search-terms/index.js` to handle reverting the excluded terms values.
* Added new event `PreResetExcludedSearchTermEvent` in` Shopware\Core\Content\Product\Events` to dispatch an event for extendable for excluded search term default.
