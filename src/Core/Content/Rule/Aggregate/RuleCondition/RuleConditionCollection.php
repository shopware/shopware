<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Aggregate\RuleCondition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class RuleConditionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'rule_condition_collection';
    }

    protected function getExpectedClass(): string
    {
        return RuleConditionEntity::class;
    }
}
