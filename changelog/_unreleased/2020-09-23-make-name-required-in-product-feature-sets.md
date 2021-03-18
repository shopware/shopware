---
title: Make field "name" required in product feature sets.
issue: NEXT-11000
author:         Jannis Leifeld
author_email:   j.leifeld@shopware.com
author_github:  @jleifeld
---
# Core
* Added `name` to required fields in `ProductFeatureSetTranslationDefinition`
* Added `\Shopware\Core\Migration\Migration1601388975RequireFeatureSetName` to implement this change on database-level
___
# Administration
* Added validation for inline editing in `sw-settings-product-feature-sets-list`
