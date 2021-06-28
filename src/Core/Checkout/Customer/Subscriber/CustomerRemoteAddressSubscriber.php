<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CustomerRemoteAddressSubscriber implements EventSubscriberInterface
{
    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        EntityRepositoryInterface $customerRepository,
        RequestStack $requestStack
    ) {
        $this->customerRepository = $customerRepository;
        $this->requestStack = $requestStack;
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
