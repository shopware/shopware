---
title: Cms Page Translation Name Nullable
issue: NEXT-15011
author: Rune Laenen
author_email: rune@laenen.me 
author_github: runelaenen
---
# Core
* Added migration `Shopware\Core\Migration\V6_4\Migration1617896006MakeNameNullable` to make `name` column in `cms_page_translation` nullable.
* Removed `Required` flag from field `name` in `Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition`
* Changed method `Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationEntity::setName()` to accept null.
