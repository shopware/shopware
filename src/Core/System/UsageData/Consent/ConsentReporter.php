<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\Event\ConsentAcceptedEvent;
use Shopware\Core\System\Consent\Event\ConsentRevokedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 */
#[Package('data-services')]
class ConsentReporter implements EventSubscriberInterface
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly InstanceService $instanceService,
        private readonly string $appUrl,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsentAcceptedEvent::class => 'reportAcceptedConsent',
            ConsentRevokedEvent::class => 'reportRevokedConsent',
        ];
    }

    public function reportAcceptedConsent(ConsentAcceptedEvent $event): void
    {
        if ($event->consentName !== BackendData::NAME) {
            return;
        }

        $this->reportConsentState('accepted');
    }

    public function reportRevokedConsent(ConsentRevokedEvent $event): void
    {
        if ($event->consentName !== BackendData::NAME) {
            return;
        }

        $this->reportConsentState('revoked');
    }

    private function reportConsentState(string $consentState): void
    {
        $payload = [
            'app_url' => $this->appUrl,
            'consent_state' => $consentState,
            'license_host' => $this->systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN),
            'shop_id' => $this->shopIdProvider->getShopId(),
            'shopware_version' => $this->instanceService->getShopwareVersion(),
        ];

        try {
            $this->client->request(
                Request::METHOD_POST,
                '/v1/consent',
                [
                    'headers' => [
                        'Shopware-Shop-Id' => $this->shopIdProvider->getShopId(),
                    ],
                    'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
                ]
            );
        } catch (\Throwable) {
        }
    }
}
