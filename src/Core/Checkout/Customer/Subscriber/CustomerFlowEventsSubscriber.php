<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexingMessage;
use Shopware\Core\Checkout\Customer\Event\CustomerChangedPaymentMethodEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('services-settings')]
class CustomerFlowEventsSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly SalesChannelContextRestorer $restorer,
        private readonly EntityIndexer $customerIndexer,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        $context = $event->getContext();
        if ($context->getSource() instanceof SalesChannelApiSource) {
            return;
        }

        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            if (!Feature::isActive('v6.7.0.0') && !empty($payload['defaultPaymentMethodId']) && empty($payload['createdAt'])) {
                $this->dispatchCustomerChangePaymentMethodEvent($payload['id'], $event);

                continue;
            }

            try {
                if (!empty($payload['createdAt'])) {
                    $this->dispatchCustomerRegisterEvent($payload['id'], $event);
                }
            } catch (SalesChannelException $exception) {
                if ($exception->getErrorCode() !== SalesChannelException::SALES_CHANNEL_LANGUAGE_NOT_AVAILABLE_EXCEPTION) {
                    throw $exception;
                }

                if ($context->getSource() instanceof AdminApiSource && \is_string($payload['id'])) {
                    $this->connection->delete('customer', ['id' => Uuid::fromHexToBytes($payload['id'])]);
                }

                throw $exception;
            }
        }
    }

    private function dispatchCustomerRegisterEvent(string $customerId, EntityWrittenEvent $event): void
    {
        $context = $event->getContext();

        $salesChannelContext = $this->restorer->restoreByCustomer($customerId, $context);
        $message = new CustomerIndexingMessage([$customerId]);
        $this->customerIndexer->handle($message);
        if (!$customer = $salesChannelContext->getCustomer()) {
            return;
        }

        $customerCreated = new CustomerRegisterEvent(
            $salesChannelContext,
            $customer
        );

        $this->dispatcher->dispatch($customerCreated);
    }

    /**
     * @deprecated tag:v6.7.0 - will be removed, customer has no default payment method anymore
     */
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
