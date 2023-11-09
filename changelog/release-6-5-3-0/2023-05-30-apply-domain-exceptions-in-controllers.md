---
title: Apply domain exceptions in controllers
issue: NEXT-27457
---
# Core
* Added new method `\Shopware\Core\Checkout\Cart\CartException::taxRuleNotFound`
* Added new methods `groupRequestNotFound`, `customersNotFound` in `\Shopware\Core\Checkout\Customer\CustomerException`
* Added new methods `promotionsNotFound`, `discountsNotFound` in `\Shopware\Core\Checkout\Promotion\PromotionException`
* Added various new methods to throw specific domain exception in `\Shopware\Core\Framework\Api\ApiException` and apply them in `\Shopware\Core\Framework\Api\` domain
* Added new domain exception class in `\Shopware\Core\Content\Category\CategoryException`
* Added new domain exception class in `\Shopware\Core\Content\Seo\SeoException`
___
# Elasticsearch
* Added new domain exception class in `\Shopware\Elasticsearch\Admin\ElasticsearchAdminException`
