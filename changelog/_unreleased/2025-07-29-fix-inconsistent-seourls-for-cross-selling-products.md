---
title: Fix inconsistent seoUrls for cross-selling products
issue: 11550
---
# Core
* Added new method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::getAll` to returns all items with the given entity and identifier
* Deprecated method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get`, please use `getAll` instead because `get` returns only the first item with could lead to other items with the same entity and identifier are ignored
___
# Upgrade Information
# Next Major Version Changes
## Deprecate method Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get:

In some occasions, the method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get` was used to retrieve a single item based on its entity and identifier. However, this method only returns the first item found, which can lead to inconsistencies when multiple items share the same entity and identifier.
So we will introduce a new method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::getAll` that returns all items with the given entity and identifier. This change ensures that all relevant items are considered, preventing potential seoUrls loss or misrepresentation.

This will lead to, if you use the method `get` in your code, you have to adapt the `getAll` method

Before

```php
$url = 'https://example.com/cross-selling/product-123';
// Only a single entity is retrieved
$entity = $data->get($definition, $url->getForeignKey());
$seoUrls = $entity->getSeoUrls();
$seoUrls->add($url);
```

After

```php
$url = 'https://example.com/cross-selling/product-123'; 
$entities = $data->getAll($definition, $url->getForeignKey());

// Now you have to loop through all entities to add the SEO URL
foreach ($entities as $entity) {
    $seoUrls = $entity->getSeoUrls();
    $seoUrls->add($url);
}
```