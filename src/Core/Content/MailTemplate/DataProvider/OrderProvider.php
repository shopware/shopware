<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\DataProvider;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Event\BeforeLoadMailDataProviderEvent;
use Shopware\Core\Content\Shared\MailFlow\OrderCriteriaBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
class OrderProvider implements DataProvider
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderCriteriaBuilder $orderCriteriaBuilder,
        private readonly EventDispatcherInterface $dispatcher
    ) {
    }

    public function getData(string $entityId, Context $context): ?OrderEntity
    {
        $criteria = $this->orderCriteriaBuilder->getCriteria($entityId);

        $event = new BeforeLoadMailDataProviderEvent(
            OrderDefinition::ENTITY_NAME,
            $criteria,
            $context,
        );

        $this->dispatcher->dispatch($event, $event->getName());

        return $this->orderRepository->search($criteria, $context)->getEntities()->get($entityId);
    }
}
