<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Event\FirstRunWizardFinishedEvent;
use Shopware\Core\Framework\Store\Event\FirstRunWizardStartedEvent;
use Shopware\Core\Framework\Store\Exception\LicenseDomainVerificationException;
use Shopware\Core\Framework\Store\Exception\StoreLicenseDomainMissingException;
use Shopware\Core\Framework\Store\Struct\AccessTokenStruct;
use Shopware\Core\Framework\Store\Struct\DomainVerificationRequestStruct;
use Shopware\Core\Framework\Store\Struct\FrwState;
use Shopware\Core\Framework\Store\Struct\LicenseDomainCollection;
use Shopware\Core\Framework\Store\Struct\LicenseDomainStruct;
use Shopware\Core\Framework\Store\Struct\PluginCategoryStruct;
use Shopware\Core\Framework\Store\Struct\PluginRecommendationCollection;
use Shopware\Core\Framework\Store\Struct\PluginRegionCollection;
use Shopware\Core\Framework\Store\Struct\PluginRegionStruct;
use Shopware\Core\Framework\Store\Struct\ShopUserTokenStruct;
use Shopware\Core\Framework\Store\Struct\StorePluginStruct;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class FirstRunWizardClient
{
    private const SHOPWARE_TOKEN_HEADER = 'X-Shopware-Token';

    private const TRACKING_EVENT_FRW_STARTED = 'First Run Wizard started';
    private const TRACKING_EVENT_FRW_FINISHED = 'First Run Wizard finished';

    private const FRW_MAX_FAILURES = 3;

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

    /**
     * @var bool
     */
    private $frwAutoRun;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        StoreService $storeService,
        SystemConfigService $configService,
        FilesystemInterface $filesystem,
        bool $frwAutoRun,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->storeService = $storeService;
        $this->client = $this->storeService->createClient();

        $this->configService = $configService;
        $this->filesystem = $filesystem;

        $this->frwAutoRun = $frwAutoRun;

        $this->eventDispatcher = $eventDispatcher;
    }

    public function startFrw(Context $context): void
    {
        $this->client->get('/ping');
        $this->storeService->fireTrackingEvent(self::TRACKING_EVENT_FRW_STARTED);

        $this->eventDispatcher->dispatch(new FirstRunWizardStartedEvent($this->getFrwState(), $context));
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

    public function upgradeAccessToken(string $storeToken, string $language): ?AccessTokenStruct
    {
        $headers = $this->client->getConfig('headers');
        $headers[self::SHOPWARE_TOKEN_HEADER] = $storeToken;

        $response = $this->client->get(
            '/swplatform/login/upgrade',
            [
                'query' => $this->storeService->getDefaultQueryParameters($language),
                'headers' => $headers,
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        $userToken = new ShopUserTokenStruct();
        $userToken->assign($data['shopUserToken']);

        $this->configService->set('core.store.shopSecret', $data['shopSecret']);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($data);
        $accessTokenStruct->setShopUserToken($userToken);

        return $accessTokenStruct;
    }

    public function finishFrw(bool $failed, Context $context): void
    {
        $currentState = $this->getFrwState();

        if ($failed) {
            $newState = FrwState::failedState();
        } else {
            $this->storeService->fireTrackingEvent(self::TRACKING_EVENT_FRW_FINISHED);
            $newState = FrwState::completedState();
        }

        $this->setFrwStatus($newState);

        $this->eventDispatcher->dispatch(new FirstRunWizardFinishedEvent($newState, $currentState, $context));
    }

    public function getFrwState(): FrwState
    {
        $completedAt = $this->configService->get('core.frw.completedAt');
        if ($completedAt) {
            return FrwState::completedState(new \DateTimeImmutable($completedAt));
        }
        $failedAt = $this->configService->get('core.frw.failedAt');
        if ($failedAt) {
            $failureCount = $this->configService->get('core.frw.failureCount') ?? 1;

            return FrwState::failedState(new \DateTimeImmutable($failedAt), $failureCount);
        }

        return FrwState::openState();
    }

    public function frwShouldRun(): bool
    {
        if (!$this->frwAutoRun) {
            return false;
        }

        $status = $this->getFrwState();
        if ($status->isCompleted()) {
            return false;
        }

        if ($status->isFailed() && $status->getFailureCount() > self::FRW_MAX_FAILURES) {
            return false;
        }

        return true;
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

        $currentLicenseDomain = $this->configService->get(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN);
        $currentLicenseDomain = $currentLicenseDomain ? idn_to_utf8($currentLicenseDomain) : null;

        $domains = array_map(static function ($data) use ($currentLicenseDomain) {
            $domain = idn_to_utf8($data['domain']);

            return (new LicenseDomainStruct())->assign([
                'domain' => $domain,
                'edition' => $data['edition']['label'],
                'verified' => $data['verified'] ?? false,
                'active' => $domain === $currentLicenseDomain,
            ]);
        }, $data);

        return new LicenseDomainCollection($domains);
    }

    public function verifyLicenseDomain(string $domain, string $language, string $storeToken, bool $testEnvironment = false): LicenseDomainStruct
    {
        $domains = $this->getLicenseDomains($language, $storeToken);

        $existing = $domains->get($domain);
        if (!$existing || !$existing->isVerified()) {
            $secret = $this->fetchVerificationInfo($domain, $language, $storeToken);
            $this->storeVerificationSecret($domain, $secret);
            $this->checkVerificationSecret($domain, $storeToken, $testEnvironment);

            $domains = $this->getLicenseDomains($language, $storeToken);
            $existing = $domains->get($domain);
        }

        if (!$existing || !$existing->isVerified()) {
            throw new LicenseDomainVerificationException($domain);
        }
        $existing->assign(['active' => true]);

        $this->configService->set(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN, $domain);
        $this->configService->set(StoreService::CONFIG_KEY_STORE_LICENSE_EDITION, $existing->getEdition());

        return $existing;
    }

    private function setFrwStatus(FrwState $newState): void
    {
        $currentState = $this->getFrwState();
        $completedAt = null;
        $failedAt = null;
        $failureCount = null;

        if ($newState->isCompleted()) {
            $completedAt = $newState->getCompletedAt()->format(\DateTimeImmutable::ATOM);
        } elseif ($newState->isFailed()) {
            $failedAt = $newState->getFailedAt()->format(\DateTimeImmutable::ATOM);
            $failureCount = $currentState->getFailureCount() + 1;
        }

        $this->configService->set('core.frw.completedAt', $completedAt);
        $this->configService->set('core.frw.failedAt', $failedAt);
        $this->configService->set('core.frw.failureCount', $failureCount);
    }

    private function checkVerificationSecret(string $domain, string $storeToken, bool $testEnvironment): void
    {
        $headers = $this->client->getConfig('headers');
        $headers[self::SHOPWARE_TOKEN_HEADER] = $storeToken;

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
        /** @var StorePluginStruct[] $mappedPlugins */
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
            throw new LicenseDomainVerificationException($domain);
        }
    }
}
