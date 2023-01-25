<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Content\Rule\Event\RuleIndexerEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class RuleEvents
{
    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent")
     */
    final public const RULE_WRITTEN_EVENT = 'rule.written';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent")
     */
    final public const RULE_DELETED_EVENT = 'rule.deleted';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent")
     */
    final public const RULE_LOADED_EVENT = 'rule.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent")
     */
    final public const RULE_SEARCH_RESULT_LOADED_EVENT = 'rule.search.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent")
     */
    final public const RULE_AGGREGATION_LOADED_EVENT = 'rule.aggregation.result.loaded';

    /**
     * @Event("Shopware\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent")
     */
    final public const RULE_ID_SEARCH_RESULT_LOADED_EVENT = 'rule.id.search.result.loaded';

    /**
     * @Event("Shopware\Core\Content\Rule\Event\RuleIndexerEvent")
     */
    final public const RULE_INDEXER_EVENT = RuleIndexerEvent::class;
}
