<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('customer-order')]
class CustomerTokenSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextPersister $contextPersister,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
            CustomerEvents::CUSTOMER_DELETED_EVENT => 'onCustomerDeleted',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            if ($writeResult->getOperation() !== EntityWriteResult::OPERATION_UPDATE) {
                continue;
            }

            $payload = $writeResult->getPayload();
            if (!$this->customerCredentialsChanged($payload)) {
                continue;
            }

            $customerId = $payload['id'];
            $newToken = $this->invalidateUsingSession($customerId);

            if ($newToken) {
                $this->contextPersister->revokeAllCustomerTokens($customerId, $newToken);
            } else {
                $this->contextPersister->revokeAllCustomerTokens($customerId);
            }
        }
    }

    public function onCustomerDeleted(EntityDeletedEvent $event): void
    {
        foreach ($event->getIds() as $customerId) {
            $this->contextPersister->revokeAllCustomerTokens($customerId);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function customerCredentialsChanged(array $payload): bool
    {
        return isset($payload['password']);
    }

    private function invalidateUsingSession(string $customerId): ?string
    {
        $master = $this->requestStack->getMainRequest();

        if (!$master) {
            return null;
        }

        // Is not a storefront request
        if (!$master->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            return null;
        }

        /** @var SalesChannelContext $context */
        $context = $master->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);

        // Not loggedin skip
        if ($context->getCustomer() === null) {
            return null;
        }

        // The written customer is not the same as logged-in. We don't modify the user session
        if ($context->getCustomer()->getId() !== $customerId) {
            return null;
        }

        $token = $context->getToken();

        $newToken = $this->contextPersister->replace($token, $context);

        $context->assign([
            'token' => $newToken,
        ]);

        if (!$master->hasSession()) {
            return null;
        }

        $session = $master->getSession();
        $session->migrate();
        $session->set('sessionId', $session->getId());

        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);
        $master->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        return $newToken;
    }
}
