---
title: Improve HreflangLoader performance
issue: NEXT-173312
---
# Core
* Changed `\Shopware\Core\Content\Seo\HreflangLoader` to use plain SQL and make use of an existing index on the `seo_url` table, thus greatly improving the performance.
* Deprecated protected method `\Shopware\Core\Content\Seo\HreflangLoader::generateHreflangHome()`, that method will be removed in v6.5.0.0, use `\Shopware\Core\Content\Seo\HreflangLoader::load()` with `route = 'frontend.home.page'` instead.
___
# Next Major Version Changes
## Refactoring of `HreflangLoader`

The protected method `\Shopware\Core\Content\Seo\HreflangLoader::generateHreflangHome()` was removed, use `\Shopware\Core\Content\Seo\HreflangLoader::load()` with `route = 'frontend.home.page'` instead.
### Before
```php
class CustomHrefLoader extends HreflangLoader
{
    public function someFunction(SalesChannelContext $salesChannelContext)
    {
        return $this->generateHreflangHome($salesChannelContext);
    }
}
```
### After
```php
class CustomHrefLoader extends HreflangLoader
{
    public function someFunction(SalesChannelContext $salesChannelContext)
    {
        return $this->load(
            new HreflangLoaderParameter('frontend.home.page', [], $salesChannelContext)
        );
    }
}
```
