---
title: Fix inconsistent seoUrls for cross-selling products
issue: 11550
---
# Core
* Added new method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::getAll` to return all items with the given entity and identifier
* Deprecated method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get`, please use `getAll` instead, as `get` returns only the first item which could lead to other items with the same entity and identifier being ignored
___
# Upgrade Information

## Deprecate method Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get

In some occasions, the method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get` was used to retrieve a single item based on its entity and identifier. However, this method only returns the first item found, which can lead to inconsistencies when multiple items share the same entity and identifier.
Because of this, we have introduced a new method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::getAll` that retrieves all items with the given entity and identifier. This change ensures that all relevant items are considered, preventing potential seoUrls loss or misrepresentation.

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

___
# Next Major Version Changes

## Remove method Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get

The method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::get` was removed as it's no longer used because it only returns the first entity found, which can lead to inconsistencies when multiple items share the same entity and identifier.
A new method `Shopware\Core\Content\Seo\SalesChannel\SeoResolverData::getAll` was introduced which returns all items with the given entity and identifier. This change ensures that all relevant items are considered, preventing potential seoUrls loss or misrepresentation.
If you use the method `get` in your code, you have to use the `getAll` method instead.

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