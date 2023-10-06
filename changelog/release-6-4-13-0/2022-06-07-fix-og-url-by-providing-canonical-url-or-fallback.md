---
title: Fix og:url by providing canonical URL or fallback
issue: NEXT-21846
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
---
# Storefront
* Changed block `layout_head_meta_tags_url_og` in `src/Storefront/Resources/views/storefront/layout/meta.html.twig` to contain canonical URL if it exists or fall back to requested URI.
