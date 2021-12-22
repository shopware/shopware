---
title: Add sales channel to seo url route interface
issue: NEXT-13410
flag: NEXT-13410
---
# Core
* Changed `\Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::prepareCriteria` to accept in future versions SalesChannelEntity as second required parameter
* Changed `\Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute::prepareCriteria` to accept in future versions SalesChannelEntity as second required parameter
* Changed `\Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute::prepareCriteria` to accept in future versions SalesChannelEntity as second required parameter
* Changed `\Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::getMapping` to accept in future versions SalesChannelEntity as second required parameter
* Changed `\Shopware\Core\Content\Seo\SeoUrlGenerator::generate` to accept in future versions SalesChannelEntity as required parameter
* Changed `\Shopware\Core\Content\Seo\SeoUrlPersister::updateSeoUrls` to accept in future versions SalesChannelEntity as required parameter

___
# Upgrade Information

## Seo url refactoring

Seo url generation will now only generate urls when the entity is also assigned to this sales channel.
To archive this `\Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface::prepareCriteria` gets as second parameter the SalesChannelEntity which will be currently proceed, to filter the criteria for this scope.

To make your Plugin already compatible for next major version you can use ReflectionClass with an if condition to avoid interface issues

$criteria->addFilter(new EqualsFilter('visibilities.salesChannelId', $salesChannel->getId()));

```php
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;

if (($r = new ReflectionClass(SeoUrlRouteInterface::class)) && $r->hasMethod('prepareCriteria') && $r->getMethod('prepareCriteria')->getNumberOfRequiredParameters() === 2) {
    class MyPluginRoute implements SeoUrlRouteInterface
    {
        public function getConfig(): SeoUrlRouteConfig
        {
            // your logic
        }
    
        public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
        {
            // your logic
        }
        
        public function getMapping(Entity $product, SalesChannelEntity $salesChannel): SeoUrlMapping
        {
            // your logic
        }
    }
} else {
    class MyPluginRoute implements SeoUrlRouteInterface
    {
        public function getConfig(): SeoUrlRouteConfig
        {
            // your logic
        }
    
        public function prepareCriteria(Criteria $criteria): void
        {
            // your logic
        }
        
        public function getMapping(Entity $product, ?SalesChannelEntity $salesChannel): SeoUrlMapping
        {
            // your logic
        }
    }
}
```
