<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerTokenSubscriber implements EventSubscriberInterface
{
    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        SalesChannelContextPersister $contextPersister,
        RequestStack $requestStack
    ) {
        $this->contextPersister = $contextPersister;
        $this->requestStack = $requestStack;
    }

    public static function getSubscribedEvents()
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
            CustomerEvents::CUSTOMER_DELETED_EVENT => 'onCustomerDeleted',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        $master = $this->requestStack->getMainRequest();

        if (!$master) {
            return;
        }

        if (!$master->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            return;
        }

        /** @var SalesChannelContext $context */
        $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $token = $context->getToken();

        $payloads = $event->getPayloads();

        foreach ($payloads as $payload) {
            if ($this->customerCredentialsChanged($payload)) {
                $newToken = $this->contextPersister->replace($token, $context);

                $context->assign([
                    'token' => $newToken,
                ]);

                if (!$master->hasSession()) {
                    return;
                }

                $session = $master->getSession();
                $session->migrate();
                $session->set('sessionId', $session->getId());

                $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);
                $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

                return;
            }
        }
    }

    public function onCustomerDeleted(EntityDeletedEvent $event): void
    {
        $master = $this->requestStack->getMainRequest();

        if (!$master) {
            return;
        }

        $customerIds = $event->getIds();

        foreach ($customerIds as $customerId) {
            $this->contextPersister->revokeAllCustomerTokens($customerId);
        }
    }

    private function customerCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']);
    }
}
