<?php

namespace Shopware\Shop\Gateway\Handler;

use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\HandlerInterface;

class ActiveConditionHandler implements HandlerInterface
{
    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return $criteriaPart instanceof ActiveCondition;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {
        $builder->andWhere('shop.active = :active');

        /** @var ActiveCondition $criteriaPart */
        $builder->setParameter('active', $criteriaPart->isActive());
    }
}