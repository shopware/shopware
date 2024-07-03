---
title: Limit character count in product search
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added migration `Migration1720000172AddMaxCharacterCountToProductSearchConfiguration` to add `max_character_count` column to `product_search_config` table.
* Added `maxCharacterCount` to `Shopware\Core\Content\Product\Aggregate\ProductSearchConfig` definition, entity and hydrator.
* Added abstract class `Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Limiter\AbstractCharacterLimiter`.
* Added service `Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Limiter\CharacterLimiter`.
* Changed method `interpret` of `Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter` to limit count of searchable characters in order to prevent exploding keyword SQL queries by using `Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Limiter\CharacterLimiter`.
___
# Administration
* Changed `sw-settings-search` component to consider `maxCharacterCount` in product search config.
* Added `maxCharacterCount` configuration field in `sw-settings-search-search-behaviour` component.
* Added snippets `sw-settings-search.generalTab.labelMaximalCharacterCount` and `sw-settings-search.generalTab.helpTextMaximalCharacterCount`.
