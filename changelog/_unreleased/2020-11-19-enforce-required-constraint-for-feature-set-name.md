---
title: Enforce required constraint for feature set name
issue: NEXT-11000
author: Philip Gatzka
author_email: p.gatzka@shopware.com 
author_github: @philipgatzka
---
# Core
* Added required flag to the `TranslationAssociationField` in `\Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition`
___
# Administration
* Changed `mapPropertyErrors` call to actually reflect the entity name in `platform/src/Administration/Resources/app/administration/src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail/index.js`
* Added `:error` properties to the following inputs in `platform/src/Administration/Resources/app/administration/src/module/sw-settings-product-feature-sets/page/sw-settings-product-feature-sets-detail/sw-settings-product-feature-sets-detail.html.twig`:
  - `.sw-settings-product-feature-sets-detail__name`
  - `.sw-settings-product-feature-sets-detail__description`
___
# Upgrade Information

In case you've been creating empty feature sets using the current faulty behaviour, please make sure to at least include
a name for any new feature set from now on.
