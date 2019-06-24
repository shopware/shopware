<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Exception\LicenseDomainVerificationException;
use Shopware\Core\Framework\Store\Exception\StoreLicenseDomainMissingException;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\DomainVerificationRequestStruct;
use Shopware\Core\Framework\Store\Struct\LicenseDomainCollection;
use Shopware\Core\Framework\Store\Struct\LicenseDomainStruct;
use Shopware\Core\Framework\Store\Struct\PluginCategoryStruct;
use Shopware\Core\Framework\Store\Struct\PluginRecommendationCollection;
use Shopware\Core\Framework\Store\Struct\PluginRegionCollection;
use Shopware\Core\Framework\Store\Struct\PluginRegionStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Store\Struct\StorePluginStruct;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class FirstRunWizardClient
{
    private const SHOPWARE_TOKEN_HEADER = 'X-Shopware-Token';

    private const TRACKING_EVENT_FRW_STARTED = 'First Run Wizard started';
    private const TRACKING_EVENT_FRW_FINISHED = 'First Run Wizard finished';

    /**
     * @var Client
     */
    private $client;

    /**
     * @var StoreService
     */
    private $storeService;

    /**
     * @var SystemConfigService
     */
    private $configService;

    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    public function __construct(StoreService $storeService, SystemConfigService $configService, FilesystemInterface $filesystem)
    {
        $this->storeService = $storeService;
        $this->client = $this->storeService->createClient();

        $this->configService = $configService;
        $this->filesystem = $filesystem;
    }

    public function startFrw(): void
    {
        $this->client->get('/ping');
        $this->storeService->fireTrackingEvent(self::TRACKING_EVENT_FRW_STARTED);
    }

    /**
     * @throws StoreLicenseDomainMissingException
     */
    public function frwLogin(string $shopwareId, string $password, string $language, string $userId): AccessTokenStruct
    {
        $response = $this->client->post(
            '/swplatform/firstrunwizard/login',
            [
                'json' => [
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                    'shopwareUserId' => $userId,
                ],
                'query' => $this->storeService->getDefaultQueryParameters($language, false),
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        $userToken = new ShopUserTokenStruct();
        $userToken->assign($data['firstRunWizardUserToken']);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($data);
        $accessTokenStruct->setShopUserToken($userToken);

        return $accessTokenStruct;
    }

    public function finishFrw(): void
    {
        $this->storeService->fireTrackingEvent(self::TRACKING_EVENT_FRW_FINISHED);

        $dateTime = (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM);
        $this->configService->set('core.frw.completedAt', $dateTime);
    }

    /**
     * @throws StoreLicenseDomainMissingException
     */
    public function getLanguagePlugins(string $language, PluginCollection $pluginCollection): array
    {
        $response = $this->client->get(
            '/swplatform/firstrunwizard/localizations',
            ['query' => $this->storeService->getDefaultQueryParameters($language, false)]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        return $this->mapPluginData($data, $pluginCollection);
    }

    /**
     * @throws StoreLicenseDomainMissingException
     */
    public function getDemoDataPlugins(string $language, PluginCollection $pluginCollection): array
    {
        $query = $this->storeService->getDefaultQueryParameters($language, false);
        $response = $this->client->get(
            '/swplatform/firstrunwizard/demodataplugins',
            ['query' => $query]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        return $this->mapPluginData($data, $pluginCollection);
    }

    /**
     * @throws StoreLicenseDomainMissingException
     */
    public function getRecommendationRegions(string $language): PluginRegionCollection
    {
        $response = $this->client->get(
            '/swplatform/firstrunwizard/categories',
            ['query' => $this->storeService->getDefaultQueryParameters($language, false)]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        $regions = new PluginRegionCollection();
        foreach ($data as $region) {
            $categories = [];
            foreach ($region['categories'] as $category) {
                if (empty($category['name']) || empty($category['label'])) {
                    continue;
                }
                $categories[] = new PluginCategoryStruct($category['name'], $category['label']);
            }
            if (empty($region['name']) || empty($region['label']) || empty($categories)) {
                continue;
            }
            $regions->add(new PluginRegionStruct($region['name'], $region['label'], $categories));
        }

        return $regions;
    }

    public function getRecommendations(string $language, PluginCollection $pluginCollection, string $region, ?string $category): PluginRecommendationCollection
    {
        $query = $this->storeService->getDefaultQueryParameters($language, false);
        $query['region'] = $query['market'] = $region;
        $query['category'] = $category;

        $response = $this->client->get(
            '/swplatform/firstrunwizard/plugins',
            ['query' => $query]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        return new PluginRecommendationCollection($this->mapPluginData($data, $pluginCollection));
    }

    public function getLicenseDomains(string $language, string $storeToken): LicenseDomainCollection
    {
        $headers = $this->client->getConfig('headers');
        $headers[self::SHOPWARE_TOKEN_HEADER] = $storeToken;

        $response = $this->client->get(
            '/swplatform/firstrunwizard/shops',
            [
                'query' => $this->storeService->getDefaultQueryParameters($language, false),
                'headers' => $headers,
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        $domains = array_map(static function ($data) {
            return (new LicenseDomainStruct())->assign([
                'domain' => $data['domain'],
                'edition' => $data['edition']['label'],
                'verified' => $data['verified'] ?? false,
            ]);
        }, $data);

        return new LicenseDomainCollection($domains);
    }

    public function verifyLicenseDomain(string $domain, string $language, string $storeToken, bool $testEnvironment = false): void
    {
        $domains = $this->getLicenseDomains($language, $storeToken);

        $existing = $domains->get($domain);
        if ($existing && $existing->isVerified()) {
            return;
        }

        $secret = $this->fetchVerificationInfo($domain, $language, $storeToken);
        $this->storeVerificationSecret($domain, $secret);
        $this->checkVerificationSecret($domain, $storeToken, $testEnvironment);

        if (!$testEnvironment) {
            $domains = $this->getLicenseDomains($language, $storeToken);
            $existing = $domains->get($domain);
            if (!$existing || !$existing->isVerified()) {
                throw new LicenseDomainVerificationException($domain);
            }
        }

        $this->configService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, $domain);
    }

    private function checkVerificationSecret(string $domain, string $storeToken, bool $testEnvironment): void
    {
        $headers = $this->client->getConfig('headers');
        $headers[self::SHOPWARE_TOKEN_HEADER] = $storeToken;

        try {
            $this->client->post(
                '/swplatform/firstrunwizard/shops',
                [
                    'json' => [
                        'domain' => $domain,
                        'shopwareVersion' => $this->storeService->getShopwareVersion(),
                        'testEnvironment' => $testEnvironment,
                    ],
                    'headers' => $headers,
                ]
            );
        } catch (\Exception $e) {
            throw new LicenseDomainVerificationException($domain, $e->getMessage());
        }
    }

    private function fetchVerificationInfo(string $domain, string $language, string $storeToken): DomainVerificationRequestStruct
    {
        $headers = $this->client->getConfig('headers');
        $headers[self::SHOPWARE_TOKEN_HEADER] = $storeToken;

        $response = $this->client->post(
            '/swplatform/firstrunwizard/shopdomainverificationhash',
            [
                'json' => ['domain' => $domain],
                'query' => $this->storeService->getDefaultQueryParameters($language, false),
                'headers' => $headers,
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        return new DomainVerificationRequestStruct($data['content'], $data['fileName']);
    }

    private function mapPluginData(array $plugins, PluginCollection $pluginCollection): array
    {
        $mappedPlugins = [];
        foreach ($plugins as $plugin) {
            if (empty($plugin['name']) || empty($plugin['localizedInfo']['name'])) {
                continue;
            }
            $mappedPlugins[] = (new StorePluginStruct())->assign([
                'name' => $plugin['name'],
                'label' => $plugin['localizedInfo']['name'],
                'shortDescription' => $plugin['localizedInfo']['shortDescription'] ?? '',

                'iconPath' => $plugin['iconPath'] ?? null,
                'category' => $plugin['language'] ?? null,
                'region' => $plugin['region'] ?? null,
                'manufacturer' => $plugin['producer']['name'] ?? null,
                'position' => $plugin['priority'] ?? null,
                'isCategoryLead' => $plugin['isCategoryLead'] ?? false,
            ]);
        }

        /** @var StorePluginStruct $storePlugin */
        foreach ($mappedPlugins as $storePlugin) {
            /** @var PluginEntity|null $plugin */
            $plugin = $pluginCollection->filterByProperty('name', $storePlugin->getName())->first();
            $storePlugin->assign([
                'active' => $plugin ? $plugin->getActive() : false,
                'installed' => $plugin ? ((bool) $plugin->getInstalledAt()) : false,
            ]);
        }

        return $mappedPlugins;
    }

    private function storeVerificationSecret(string $domain, DomainVerificationRequestStruct $validationRequest): void
    {
        try {
            $this->filesystem->put($validationRequest->getFileName(), $validationRequest->getContent());
        } catch (\Exception $e) {
            throw new LicenseDomainVerificationException($domain, $e->getMessage());
        }
    }
}
