---
title: Fix SEO URLs for landing pages in footer navigation
issue: #3784
---
# Core
* Added SEO URL generation for internal links in `sales_channel.category` entities and used that instead of `category_url` twig function
* Added replacing of seo URL and media URL placeholder for all store api responses
___ 
# Storefront
* Deprecated `category_url` and `category_linknewtab` twig function, use `category.seoUrl` or `category.shouldOpenInNewTab` instead
