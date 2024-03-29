<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category;

use Shopware\Core\Content\Category\Event\CategoryIndexerEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class CategoryEvents
{
    final public const CATEGORY_WRITTEN_EVENT = 'category.written';

    final public const CATEGORY_DELETED_EVENT = 'category.deleted';

    final public const CATEGORY_LOADED_EVENT = 'category.loaded';

    final public const CATEGORY_SEARCH_RESULT_LOADED_EVENT = 'category.search.result.loaded';

    final public const CATEGORY_AGGREGATION_LOADED_EVENT = 'category.aggregation.result.loaded';

    final public const CATEGORY_ID_SEARCH_RESULT_LOADED_EVENT = 'category.id.search.result.loaded';

    final public const CATEGORY_TRANSLATION_WRITTEN_EVENT = 'category_translation.written';

    final public const CATEGORY_TRANSLATION_DELETED_EVENT = 'category_translation.deleted';

    final public const CATEGORY_TRANSLATION_LOADED_EVENT = 'category_translation.loaded';

    final public const CATEGORY_TRANSLATION_SEARCH_RESULT_LOADED_EVENT = 'category_translation.search.result.loaded';

    final public const CATEGORY_TRANSLATION_AGGREGATION_LOADED_EVENT = 'category_translation.aggregation.result.loaded';

    final public const CATEGORY_TRANSLATION_ID_SEARCH_RESULT_LOADED_EVENT = 'category_translation.id.search.result.loaded';

    final public const CATEGORY_INDEXER_EVENT = CategoryIndexerEvent::class;
}
