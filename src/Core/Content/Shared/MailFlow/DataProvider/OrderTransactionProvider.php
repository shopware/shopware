<?php declare(strict_types=1);

namespace Shopware\Core\Content\Shared\MailFlow\DataProvider;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<OrderTransactionEntity, OrderTransactionCollection>
 */
#[Package('after-sales')]
class OrderTransactionProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return OrderTransactionDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}
