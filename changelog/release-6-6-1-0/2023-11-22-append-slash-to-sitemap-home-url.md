---
title: Append slash to sitemap home url
issue: NEXT-33833
author: Benny Poensgen
author_email: poensgen@vanwittlaer.de
author_github: @vanwittlaer
---
# Core
* Changed `\Shopware\Core\Content\Sitemap\Service\SitemapExporter.php`to append a slash to the home url in the sitemap. This is required to determine the base url correctly, in particular when a sales channel domain contains an url slug (like `.../de/`.)
