---
title: Fix double slash in sitemap urls for entities without seo url
issue: NEXT-38705
author: Benny Poensgen
author_email: poensgen@vanwittlaer.de
author_github: @vanwittlaer
---
# Core
* Changed `SitemapExporter` to force-trim leading slash in entity urls