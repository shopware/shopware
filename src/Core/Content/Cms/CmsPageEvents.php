<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class CmsPageEvents
{
    final public const PAGE_WRITTEN_EVENT = 'cms_page.written';

    final public const PAGE_DELETED_EVENT = 'cms_page.deleted';

    final public const PAGE_LOADED_EVENT = 'cms_page.loaded';

    final public const PAGE_SEARCH_RESULT_LOADED_EVENT = 'cms_page.search.result.loaded';

    final public const PAGE_AGGREGATION_LOADED_EVENT = 'cms_page.aggregation.result.loaded';

    final public const PAGE_ID_SEARCH_RESULT_LOADED_EVENT = 'cms_page.id.search.result.loaded';

    final public const BLOCK_WRITTEN_EVENT = 'cms_block.written';

    final public const BLOCK_DELETED_EVENT = 'cms_block.deleted';

    final public const BLOCK_LOADED_EVENT = 'cms_block.loaded';

    final public const BLOCK_SEARCH_RESULT_LOADED_EVENT = 'cms_block.search.result.loaded';

    final public const BLOCK_AGGREGATION_LOADED_EVENT = 'cms_block.aggregation.result.loaded';

    final public const BLOCK_ID_SEARCH_RESULT_LOADED_EVENT = 'cms_block.id.search.result.loaded';

    final public const SLOT_WRITTEN_EVENT = 'cms_slot.written';

    final public const SLOT_DELETED_EVENT = 'cms_slot.deleted';

    final public const SLOT_LOADED_EVENT = 'cms_slot.loaded';

    final public const SLOT_SEARCH_RESULT_LOADED_EVENT = 'cms_slot.search.result.loaded';

    final public const SLOT_AGGREGATION_LOADED_EVENT = 'cms_slot.aggregation.result.loaded';

    final public const SLOT_ID_SEARCH_RESULT_LOADED_EVENT = 'cms_slot.id.search.result.loaded';
}
