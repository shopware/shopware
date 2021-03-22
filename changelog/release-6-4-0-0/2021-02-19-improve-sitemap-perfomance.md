---
title: Improve Sitemap performance
issue: NEXT-9987
---
# Core
* Deprecated interface `Shopware\Core\Content\Sitemap\Provider\UrlProviderInterface` to use abstract AbstractUrlProvider at `Shopware/Core/Content/Sitemap/Provider/AbstractUrlProvider`
* Changed all Provider class to extends AbstractUrlProvider instead UrlProviderInterface:
    * `Shopware\Core\Content\Sitemap\Provider\CategoryUrlProvider`
    * `Shopware\Core\Content\Sitemap\Provider\CustomUrlProvider`
    * `Shopware\Core\Content\Sitemap\Provider\HomeUrlProvider`
    * `Shopware\Core\Content\Sitemap\Provider\ProductUrlProvider`
    
* Change return URl value of method CategoryUrlProvider:getUrls and ProductUrlProvider:getUrls:
    Before:
    * `124c71d524604ccbad6042edce3ac799/detail/0fd25a24b8724b8aab896292ba1c04a4#`
    After:
    * `test-product-sitemap/2fbb5fe2e29a4d70aa5854ce7ce3e20b/test-product-1/aaa1221c69284069918f5bed72b827e6`

* Changed `Shopware\Core\Content\Sitemap\ScheduledTask\SitemapGenerateTaskHandler:run` for handle the dispatch message base on sales-channel and languages
* Changed `Shopware\Core\Content\Sitemap\Service\SitemapExporter::generate` to support export multi domains with same languages
* Added validation session_status() === \PHP_SESSION_ACTIVE at route SitemapRoute:load
