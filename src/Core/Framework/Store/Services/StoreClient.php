<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Context\AdminApiSource;
use Shopware\Core\Framework\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\PluginDownloadDataStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseSubscriptionStruct;
use Shopware\Core\Framework\Store\Struct\StoreLicenseTypeStruct;
use Shopware\Core\Framework\Store\Struct\StoreUpdateStruct;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class StoreClient
{
    private const SHOPWARE_PLATFORM_TOKEN_HEADER = 'X-Shopware-Platform-Token';

    private const SHOPWARE_SHOP_SECRET_HEADER = 'X-Shopware-Shop-Secret';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var EntityRepositoryInterface
     */
    private $pluginRepo;

    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var StoreService
     */
    private $storeService;

    public function __construct(
        StoreService $storeService,
        EntityRepositoryInterface $pluginRepo,
        SystemConfigService $configService
    ) {
        $this->storeService = $storeService;
        $this->configService = $configService;
        $this->pluginRepo = $pluginRepo;

        $this->client = $this->storeService->createClient();
    }

    public function ping(): void
    {
        $this->client->get('/ping');
    }

    public function loginWithShopwareId(string $shopwareId, string $password, string $language, Context $context): AccessTokenStruct
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $response = $this->client->post(
            '/swplatform/login',
            [
                'body' => \json_encode([
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                    'shopwareUserId' => $context->getSource()->getUserId(),
                ]),
                'query' => $this->storeService->getDefaultQueryParameters($language),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $userToken = new ShopUserTokenStruct();
        $userToken->assign($data['shopUserToken']);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($data);
        $accessTokenStruct->setShopUserToken($userToken);

        return $accessTokenStruct;
    }

    /**
     * @return StoreLicenseStruct[]
     */
    public function getLicenseList(string $storeToken, string $language, Context $context): array
    {
        $shopSecret = $this->getShopSecret();

        $headers = $this->client->getConfig('headers');
        $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER] = $storeToken;
        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        $response = $this->client->get(
            '/swplatform/pluginlicenses',
            [
                'query' => $this->storeService->getDefaultQueryParameters($language),
                'headers' => $headers,
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $licenseList = [];
        $installedPlugins = [];

        /** @var PluginCollection $pluginCollection */
        $pluginCollection = $this->pluginRepo->search(new Criteria(), $context)->getEntities();

        /** @var PluginEntity $plugin */
        foreach ($pluginCollection as $plugin) {
            $installedPlugins[$plugin->getName()] = $plugin->getVersion();
        }

        foreach ($data['data'] as $license) {
            $licenseStruct = new StoreLicenseStruct();
            $licenseStruct->assign($license);

            $licenseStruct->setInstalled(array_key_exists($licenseStruct->getTechnicalPluginName(), $installedPlugins));
            if (isset($license['availableVersion'])) {
                if ($licenseStruct->getInstalled()) {
                    $installedVersion = $installedPlugins[$licenseStruct->getTechnicalPluginName()];

                    $licenseStruct->setUpdateAvailable(version_compare($installedVersion, $licenseStruct->getAvailableVersion()) === -1);
                } else {
                    $licenseStruct->setUpdateAvailable(false);
                }
            }
            if (isset($license['type']['name'])) {
                $type = new StoreLicenseTypeStruct();
                $type->assign($license['type']);
                $licenseStruct->setType($type);
            }
            if (isset($license['subscription']['expirationDate'])) {
                $subscription = new StoreLicenseSubscriptionStruct();
                $subscription->assign($license['subscription']);
                $licenseStruct->setSubscription($subscription);
            }

            $licenseList[] = $licenseStruct;
        }

        return $licenseList;
    }

    /**
     * @return StoreUpdateStruct[]
     */
    public function getUpdatesList(?string $storeToken, PluginCollection $pluginCollection, string $language, string $hostName, Context $context): array
    {
        $pluginArray = [];

        /** @var PluginEntity $plugin */
        foreach ($pluginCollection as $plugin) {
            $pluginArray[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        $shopSecret = $this->getShopSecret();

        $headers = $this->client->getConfig('headers');
        if ($storeToken) {
            $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER] = $storeToken;
        }
        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        $query = $this->storeService->getDefaultQueryParameters($language, false);
        $query['hostName'] = $hostName;

        $response = $this->client->post(
            '/swplatform/pluginupdates',
            [
                'query' => $query,
                'body' => json_encode([
                    'plugins' => $pluginArray,
                ]),
                'headers' => $headers,
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $updateList = [];
        foreach ($data['data'] as $update) {
            $updateStruct = new StoreUpdateStruct();
            $updateStruct->assign($update);
            $updateList[] = $updateStruct;
        }

        return $updateList;
    }

    public function getDownloadDataForPlugin(string $pluginName, string $storeToken, string $language, bool $checkLicenseDomain = true): PluginDownloadDataStruct
    {
        $shopSecret = $this->getShopSecret();

        $headers = [];

        if (!empty($storeToken)) {
            $headers[self::SHOPWARE_PLATFORM_TOKEN_HEADER] = $storeToken;
        }

        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        $response = $this->client->get(
            '/swplatform/pluginfiles/' . $pluginName,
            [
                'query' => $this->storeService->getDefaultQueryParameters($language, $checkLicenseDomain),
                'headers' => array_merge(
                    $this->client->getConfig('headers'),
                    $headers
                ),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);
        $dataStruct = new PluginDownloadDataStruct();
        $dataStruct->assign($data);

        return $dataStruct;
    }

    public function getPluginCompatibilities(string $futureVersion, string $language, PluginCollection $pluginCollection): array
    {
        $pluginArray = [];

        /** @var PluginEntity $plugin */
        foreach ($pluginCollection as $plugin) {
            $pluginArray[] = [
                'name' => $plugin->getName(),
                'version' => $plugin->getVersion(),
            ];
        }

        $shopSecret = $this->getShopSecret();

        $headers = [];
        if ($shopSecret) {
            $headers[self::SHOPWARE_SHOP_SECRET_HEADER] = $shopSecret;
        }

        $response = $this->client->post('/swplatform/autoupdate', [
            'query' => $this->storeService->getDefaultQueryParameters($language, false),
            'headers' => array_merge(
                $this->client->getConfig('headers'),
                $headers
            ),
            'json' => [
                'futureShopwareVersion' => $futureVersion,
                'plugins' => $pluginArray,
            ],
        ]);

        return json_decode((string) $response->getBody(), true);
    }

    public function isShopUpgradeable(): bool
    {
        $response = $this->client->get('/swplatform/autoupdate/permission', [
            'query' => $this->storeService->getDefaultQueryParameters('en-GB', false),
            'headers' => $this->client->getConfig('headers'),
        ]);

        return json_decode((string) $response->getBody(), true)['updateAllowed'];
    }

    private function getShopSecret(): ?string
    {
        return $this->configService->get('core.store.shopSecret');
    }
}
