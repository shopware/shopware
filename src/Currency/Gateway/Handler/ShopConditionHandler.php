<?php

namespace Shopware\Currency\Gateway\Handler;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Search\Condition\ShopCondition;
use Shopware\Search\Criteria;
use Shopware\Search\CriteriaPartInterface;
use Shopware\Search\HandlerInterface;

class ShopConditionHandler implements HandlerInterface
{
    public function supports(CriteriaPartInterface $criteriaPart): bool
    {
        return $criteriaPart instanceof ShopCondition;
    }

    public function handle(
        CriteriaPartInterface $criteriaPart,
        QueryBuilder $builder,
        Criteria $criteria,
        TranslationContext $context
    ): void {

        $builder->innerJoin('currency', 's_core_shop_currencies', 'shopCurrencies', 'shopCurrencies.currency_id = currency.id AND shopCurrencies.shop_id IN (:shopIds)');

        /** @var ShopCondition $criteriaPart */
        $builder->setParameter('shopIds', $criteriaPart->getUuids(), Connection::PARAM_INT_ARRAY);
    }
}