<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\Aggregate\RuleCondition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(RuleConditionEntity $entity)
 * @method void                     set(string $key, RuleConditionEntity $entity)
 * @method RuleConditionEntity[]    getIterator()
 * @method RuleConditionEntity[]    getElements()
 * @method RuleConditionEntity|null get(string $key)
 * @method RuleConditionEntity|null first()
 * @method RuleConditionEntity|null last()
 */
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
