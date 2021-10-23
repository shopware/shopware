---
title: Make maximum number of URLs in a sitemap file configurable
author: Julian Krzefski
author_email: krzefski@heptacom.de
author_github: jkrzefski
---

# Core

* Added configuration option `shopware.sitemap.max_urls`. The value defaults to `null`, resulting in a fallback to `49999` (see `\Shopware\Core\Content\Sitemap\Service\SitemapHandle::MAX_URLS`).

___

# Upgrade Information

* You can now configure `shopware.sitemap.max_urls` to configure the maximum number of urls in a sitemap file. This can be useful if the default of `49999` leads to broken or empty sitemap files in your hosting environment.
