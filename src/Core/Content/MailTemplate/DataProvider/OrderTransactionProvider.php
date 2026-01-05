<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\DataProvider;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Content\Shared\MailFlow\Event\MailFlowDataCriteriaEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderTransactionProvider implements DataProvider
{
    /**
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getData(string $entityId, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria([$entityId]);

        $event = new MailFlowDataCriteriaEvent(
            OrderTransactionDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        return $this->orderTransactionRepository->search($criteria, $context)->getEntities()->get($entityId);
    }
}
