<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @internal
 *
 * @phpstan-import-type AccessKeys from ConsentService
 */
#[Package('merchant-services')]
class ConsentReporter
{
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly InstanceService $instanceService,
    ) {
    }

    /**
     * @param AccessKeys|null $accessKeys
     */
    public function reportConsent(ConsentState $consentState, ?array $accessKeys = null): void
    {
        $payload = [
            'consent_state' => $consentState->value,
            'license_host' => $this->systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN),
            'shop_id' => $this->shopIdProvider->getShopId(),
            'shopware_version' => $this->instanceService->getShopwareVersion(),
        ];

        if ($accessKeys !== null) {
            $payload['api_credential'] = [
                'app_url' => $accessKeys['appUrl'],
                'access_key' => $accessKeys['accessKey'],
                'secret_access_key' => $accessKeys['secretAccessKey'],
            ];
        }

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
    }

    /**
     * @param AccessKeys $accessKeys
     */
    public function reportConsentIntegrationAppUrlChanged(string $shopId, array $accessKeys): void
    {
        $payload = [
            'license_host' => $this->systemConfigService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN),
            'shop_id' => $shopId,
            'shopware_version' => $this->instanceService->getShopwareVersion(),
            'api_credential' => [
                'app_url' => $accessKeys['appUrl'],
                'access_key' => $accessKeys['accessKey'],
                'secret_access_key' => $accessKeys['secretAccessKey'],
            ],
        ];

        $this->client->request(
            Request::METHOD_POST,
            '/v1/app-url-changed',
            [
                'headers' => [
                    'Shopware-Shop-Id' => $shopId,
                ],
                'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
            ]
        );
    }
}
