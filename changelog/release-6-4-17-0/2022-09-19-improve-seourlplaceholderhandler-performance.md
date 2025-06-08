---
title: Improve SeoUrlPlaceholderHandler performance
issue: NEXT-23271
author: Rafael Kraut
author_email: rk@vi-arise.com
author_github: RafaelKr
---
# Core
* Changed `Shopware\Core\Content\Seo\SeoUrlPlaceholderHandler::createDefaultMapping()` to use `substr()` instead of `str_replace()`
