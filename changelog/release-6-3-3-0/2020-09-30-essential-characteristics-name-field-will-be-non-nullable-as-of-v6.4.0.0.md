---
title: Essential characteristics name field will be non-nullable as of v6.4.0.0
issue: NEXT-11000
author: Philip Gatzka
author_email: p.gatzka@shopware.com 
author_github: @philipgatzka
---
# Core

# Upgrade Information

## `name` attribute of `ProductFeatureSetTranslationDefinition` will be non-nullable

With [4456](https://github.com/shopware/shopware/issues/4456), the `name` attribute in
[ProductFeatureSetTranslationDefinition](https://github.com/shopware/platform/blob/master/src/Core/Content/Product/Aggregate/ProductFeatureSetTranslation/ProductFeatureSetTranslationDefinition.php)
was marked non-nullable. This change is also implemented on database-level with
[Migration1601388975RequireFeatureSetName.php](https://github.com/shopware/platform/blob/master/src/Core/Migration/Migration1601388975RequireFeatureSetName.php).
For blue-green deployment compatibility, the now non-nullable field will have an empty string as default value.
