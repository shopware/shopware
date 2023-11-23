---
title: Add default cms pages to products and categories
date: 2022-04-06
area: content
tags: [cms, product, category]
---

## Context

We want to provide a way to set a default cms page.  
Currently, if a product has no cms page assigned to it, there is a hardcoded fallback twig-template but no default. This does not apply to categories.

Products and categories should both get a default cms page if none is assigned which can be configured by the user.  
The default should only be used when the corresponding entity is marked to use a default.

## Decision

In order to implement this functionality, we needed a way to mark a product or category, which should use the default. This affects following entities:
* category
  * `category.cmsPageId` will be set to null if the defined default is given,
  * this only applies to cms pages of type `procut_list`
* product
  * `product.cmsPageId` will be set to null if the defined default `\Shopware\Core\Defaults::CMS_PRODUCT_DETAIL_PAGE` is given.

In order to set the default cms page, we simply provide the corresponding cms page id in the system config.
* `\Shopware\Core\Content\Product\ProductDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_PRODUCT` for the default cms page for products
* `\Shopware\Core\Content\Category\CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY` for the default cms page for categories of type `product_list`

This fallback is loaded at the `entity.loaded` event of the corresponding entity, where we check if the foreign key is set to NULL and inject the corresponding system config as default.

## Consequences

For all related entities, the `cmsPageId` will be set to `null` if the default is given.
The corresponding cms page id will then be set by subscribers.
Therefor the cms page id, which is stored in the database, might differ from the cms page id which can be received via repository.  
To use the default functionality, an entity must not have a cms page id assigned to it.  
