<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Util;

use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;

class EventIdExtractor
{
    public function getRuleIds(EntityWrittenContainerEvent $generic): array
    {
        $ids = [];

        $event = $generic->getEventByEntityName(RuleDefinition::ENTITY_NAME);
        if ($event) {
            $ids = $event->getIds();
        }

        $event = $generic->getEventByEntityName(RuleConditionDefinition::ENTITY_NAME);
        if ($event) {
            foreach ($event->getPayloads() as $id) {
                if (isset($id['ruleId'])) {
                    $ids[] = $id['ruleId'];
                }
            }
        }

        return $ids;
    }
}
