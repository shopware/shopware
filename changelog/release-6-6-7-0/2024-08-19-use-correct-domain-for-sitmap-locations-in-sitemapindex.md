---
title: Use correct Domain for Sitemap locations in SitemapIndex
issue: NEXT-37521
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: @jozsefdamokos
---
# Core
* Changed sitemap file naming and prepended domainId in `SitemapHandle`.
* Changed URL in loc attribute of sitemap index in `SitemapLister` so that it uses the domain URL.
* Deprecated `SitemapHandleFactoryInterface::create` method as new optional parameter `$domainId` will be added.
* Deprecated `SitemapHandleFactory::create` method as new optional parameter `$domainId` will be added.
___
# API
* Added new route `/store-api/sitemap/{filePath}` to download sitemap files from the configured storage.
___
# Storefront
* Added new route `/sitemap/{filePath}` to proxy sitemap requests and download sitemap files from the configured storage.
___
# Next Major Version Changes
## SitemapHandleFactoryInterface::create

We added a new optional parameter `string $domainId` to `SitemapHandleFactoryInterface::create` and `SitemapHandleFactory::create`.
If you implement the `SitemapHandleFactoryInterface` or extend the `SitemapHandleFactory` class, you should properly handle the new parameter in your custom implementation.
