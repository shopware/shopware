<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('customer-order')]
class CustomerRemoteAddressSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'updateRemoteAddressByLogin',
        ];
    }

    public function updateRemoteAddressByLogin(CustomerLoginEvent $event): void
    {
        $request = $this->requestStack
            ->getMainRequest();

        if (!$request) {
            return;
        }

        $this->customerRepository->update([
            [
                'id' => $event->getCustomer()->getId(),
                'remoteAddress' => $request->getClientIp(),
            ],
        ], $event->getContext());
    }
}
