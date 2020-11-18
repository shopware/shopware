<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerMetaFieldSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function __construct(EntityRepositoryInterface $orderRepository, EntityRepositoryInterface $customerRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->customerRepository = $customerRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderEvents::ORDER_WRITTEN_EVENT => 'fillCustomerMetaDataFields',
        ];
    }

    public function fillCustomerMetaDataFields(EntityWrittenEvent $event): void
    {
        if ($event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        $context = $event->getContext();

        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getExistence() !== null && $writeResult->getExistence()->exists()) {
                break;
            }

            $payload = $writeResult->getPayload();
            if (empty($payload)) {
                continue;
            }

            /** @var \DateTimeInterface $orderDate */
            $orderDate = $payload['orderDateTime'];

            $orderResult = $this->orderRepository->search(
                (new Criteria([$payload['id']]))->addAssociation('orderCustomer'),
                $context
            );

            /** @var OrderEntity|null $order */
            $order = $orderResult->first();

            if (!($order instanceof OrderEntity)) {
                continue;
            }

            $orderCustomer = $order->getOrderCustomer();
            if ($orderCustomer === null) {
                continue;
            }
            $customerId = $orderCustomer->getCustomerId();

            // happens if the customer was deleted
            if (!$customerId) {
                continue;
            }

            $orderCount = 0;

            $customerResult = $this->customerRepository->search(
                (new Criteria([$customerId]))->addAssociation('orderCustomers'),
                $context
            );

            /** @var CustomerEntity $customer */
            $customer = $customerResult->first();

            if ($customer !== null && $customer->getOrderCustomers()) {
                $orderCount = $customer->getOrderCustomers()->count();
            }

            $data = [
                [
                    'id' => $customerId,
                    'orderCount' => $orderCount,
                    'lastOrderDate' => $orderDate->format('Y-m-d H:i:s.v'),
                ],
            ];

            $context->scope(Context::SYSTEM_SCOPE, function () use ($data, $context): void {
                $this->customerRepository->update($data, $context);
            });
        }
    }
}
