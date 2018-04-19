<?php

namespace Shopware\Shop\Gateway\Handler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\ParentCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\HandlerInterface;

class ParentConditionHandler implements HandlerInterface
{
    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return $criteriaPart instanceof ParentCondition;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {
        $builder->andWhere('shop.main_id IN (:mainIds)');

        /** @var ParentCondition $criteriaPart */
        $builder->setParameter('mainIds', $criteriaPart->getParentIds(), Connection::PARAM_INT_ARRAY);
    }
}