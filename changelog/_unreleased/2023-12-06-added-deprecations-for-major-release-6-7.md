---
title: Added deprecations for major release 6.7
issue: NEXT-31184
author: Krzykawski
author_email: m.krzykawski@shopware.com
author_github: Krzykawski
---
# Core
* Deprecated `Shopware\Core\Checkout\Promotion\Exception\PromotionCodeNotFoundException`. It will be removed in v6.7.0. Use `Shopware\Core\Checkout\Promotion\PromotionException::promotionCodeNotFound` instead.
___
# Administration
* Deprecated `src/module/sw-settings-search/component/sw-settings-search-search-index/index.js`. It will be changed to private in v6.7.0.
