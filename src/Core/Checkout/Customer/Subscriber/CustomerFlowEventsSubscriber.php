<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CustomerFlowEventsSubscriber implements EventSubscriberInterface
{
    private EventDispatcherInterface $dispatcher;

    private SalesChannelContextRestorer $restorer;

    /**
     * @internal
     */
    public function __construct(
        EventDispatcherInterface $dispatcher,
        SalesChannelContextRestorer $restorer
    ) {
        $this->dispatcher = $dispatcher;
        $this->restorer = $restorer;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents()
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        if ($event->getContext()->getSource() instanceof SalesChannelApiSource) {
            return;
        }

        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            if (!empty($payload['defaultPaymentMethodId']) && empty($payload['createdAt'])) {
                $this->dispatchCustomerChangePaymentMethodEvent($payload['id'], $event);

                continue;
            }

            if (!empty($payload['createdAt'])) {
                $this->dispatchCustomerRegisterEvent($payload['id'], $event);
            }
        }
    }

    private function dispatchCustomerRegisterEvent(string $customerId, EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        $salesChannelContext = $this->restorer->restoreByCustomer($customerId, $context);

        if (!$customer = $salesChannelContext->getCustomer()) {
            return;
        }

        $customerCreated = new CustomerRegisterEvent(
            $salesChannelContext,
            $customer
        );

        $this->dispatcher->dispatch($customerCreated);
    }

    private function dispatchCustomerChangePaymentMethodEvent(string $customerId, EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        $salesChannelContext = $this->restorer->restoreByCustomer($customerId, $context);

        if (!$customer = $salesChannelContext->getCustomer()) {
            return;
        }

        $customerChangePaymentMethodEvent = new CustomerChangedPaymentMethodEvent(
            $salesChannelContext,
            $customer,
            new RequestDataBag()
        );

        $this->dispatcher->dispatch($customerChangePaymentMethodEvent);
    }
}
