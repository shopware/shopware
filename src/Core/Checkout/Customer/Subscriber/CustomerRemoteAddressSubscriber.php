<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
readonly class CustomerRemoteAddressSubscriber implements EventSubscriberInterface
{
    private const STORE_PLAIN_IP_ADDRESS = 'core.loginRegistration.customerIpAddressesNotAnonymously';

    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private RequestStack $requestStack,
        private SystemConfigService $configService
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

        $clientIp = $request->getClientIp();

        if ($clientIp === null) {
            return;
        }

        if (!$this->configService->getBool(self::STORE_PLAIN_IP_ADDRESS)) {
            $clientIp = IpUtils::anonymize($clientIp);
        }

        $this->connection->update('customer', [
            'remote_address' => $clientIp,
        ], [
            'id' => Uuid::fromHexToBytes($event->getCustomer()->getId()),
        ]);
    }
}
