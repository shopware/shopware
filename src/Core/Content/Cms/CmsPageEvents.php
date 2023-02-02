<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Framework\Log\Package;

#[Package('content')]
class CmsPageEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const PAGE_WRITTEN_EVENT = 'cms_page.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const PAGE_DELETED_EVENT = 'cms_page.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const PAGE_LOADED_EVENT = 'cms_page.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const PAGE_SEARCH_RESULT_LOADED_EVENT = 'cms_page.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const PAGE_AGGREGATION_LOADED_EVENT = 'cms_page.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const PAGE_ID_SEARCH_RESULT_LOADED_EVENT = 'cms_page.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const BLOCK_WRITTEN_EVENT = 'cms_block.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const BLOCK_DELETED_EVENT = 'cms_block.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const BLOCK_LOADED_EVENT = 'cms_block.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const BLOCK_SEARCH_RESULT_LOADED_EVENT = 'cms_block.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const BLOCK_AGGREGATION_LOADED_EVENT = 'cms_block.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const BLOCK_ID_SEARCH_RESULT_LOADED_EVENT = 'cms_block.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const SLOT_WRITTEN_EVENT = 'cms_slot.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const SLOT_DELETED_EVENT = 'cms_slot.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const SLOT_LOADED_EVENT = 'cms_slot.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const SLOT_SEARCH_RESULT_LOADED_EVENT = 'cms_slot.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const SLOT_AGGREGATION_LOADED_EVENT = 'cms_slot.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const SLOT_ID_SEARCH_RESULT_LOADED_EVENT = 'cms_slot.id.search.result.loaded';
}
