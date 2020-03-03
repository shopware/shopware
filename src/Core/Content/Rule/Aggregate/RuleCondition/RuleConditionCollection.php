<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Aggregate\RuleCondition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class RuleConditionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return RuleConditionEntity::class;
    }
}
