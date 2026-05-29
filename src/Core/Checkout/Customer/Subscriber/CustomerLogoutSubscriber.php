<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextTokenChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerLogoutSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLogoutEvent::class => [
                ['refreshContextToken', 10000],
                ['removeImitatingUserId', -10000],
            ],
        ];
    }

    public function refreshContextToken(CustomerLogoutEvent $event): void
    {
        $oldContext = $event->getSalesChannelContext();

        $newContext = $this->contextService->get(
            new SalesChannelContextServiceParameters(
                $oldContext->getSalesChannelId(),
                SalesChannelContextService::getNewToken(),
            )
        );

        $event->setSalesChannelContext($newContext);

        $this->eventDispatcher->dispatch(new SalesChannelContextTokenChangeEvent($newContext, $oldContext->getToken(), $newContext->getToken()));
    }

    public function removeImitatingUserId(CustomerLogoutEvent $event): void
    {
        $event->getSalesChannelContext()->setImitatingUserId(null);

        if (Feature::isActive('v6.8.0.0') || Feature::isActive('MULTI_CONTEXT_TOKENS')) {
            return;
        }

        $mainRequest = $this->requestStack->getMainRequest();
        if (!$mainRequest?->hasSession()) {
            return;
        }

        $mainRequest->getSession()->remove(PlatformRequest::ATTRIBUTE_IMITATING_USER_ID);
    }
}
