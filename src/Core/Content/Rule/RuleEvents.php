<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule;

use Shopware\Core\Content\Rule\Event\RuleIndexerEvent;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class RuleEvents
{
    final public const RULE_WRITTEN_EVENT = 'rule.written';

    final public const RULE_DELETED_EVENT = 'rule.deleted';

    final public const RULE_LOADED_EVENT = 'rule.loaded';

    final public const RULE_SEARCH_RESULT_LOADED_EVENT = 'rule.search.result.loaded';

    final public const RULE_AGGREGATION_LOADED_EVENT = 'rule.aggregation.result.loaded';

    final public const RULE_ID_SEARCH_RESULT_LOADED_EVENT = 'rule.id.search.result.loaded';

    final public const RULE_INDEXER_EVENT = RuleIndexerEvent::class;
}
