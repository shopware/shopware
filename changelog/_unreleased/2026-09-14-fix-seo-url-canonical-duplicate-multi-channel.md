---
title: Fix duplicate-key error when generating SEO URLs for products in multiple same-language sales channels
issue: 20396
author: Wael Golli
author_email: golli@holzlandbecker.de
author_github: golliholzland
---
# Core
* Changed `Shopware\Core\Content\Seo\SeoUrlPersister::updateCanonicalSeoUrls` to only promote an SEO URL to canonical when no canonical row already exists for the same language, sales channel, foreign key and route. This prevents a `1062` duplicate-key error on `seo_url.uniq.seo_url.foreign_key` when re-indexing products assigned to multiple sales channels sharing a language.
