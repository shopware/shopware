<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Authentication\AbstractStoreRequestOptionsProvider;
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

/**
 * @internal
 */
final class FirstRunWizardClient
{
    private const TRACKING_EVENT_FRW_STARTED = 'First Run Wizard started';
    private const TRACKING_EVENT_FRW_FINISHED = 'First Run Wizard finished';

    private const SYSTEM_CONFIG_KEY_SHOPWARE_ID = 'core.store.shopwareId';

    private const FRW_MAX_FAILURES = 3;

    private Client $client;

    private StoreService $storeService;

    private SystemConfigService $configService;

    private FilesystemInterface $filesystem;

    private bool $frwAutoRun;

    private EventDispatcherInterface $eventDispatcher;

    private AbstractStoreRequestOptionsProvider $optionsProvider;

    private InstanceService $instanceService;

    public function __construct(
        StoreService $storeService,
        SystemConfigService $configService,
        FilesystemInterface $filesystem,
        bool $frwAutoRun,
        EventDispatcherInterface $eventDispatcher,
        Client $client,
        AbstractStoreRequestOptionsProvider $optionsProvider,
        InstanceService $instanceService
    ) {
        $this->storeService = $storeService;
        $this->client = $client;
        $this->optionsProvider = $optionsProvider;
        $this->instanceService = $instanceService;

        $this->configService = $configService;
        $this->filesystem = $filesystem;

        $this->frwAutoRun = $frwAutoRun;

        $this->eventDispatcher = $eventDispatcher;
    }

    public function startFrw(Context $context): void
    {
        $this->fireTrackingEvent(self::TRACKING_EVENT_FRW_STARTED);

        $this->eventDispatcher->dispatch(new FirstRunWizardStartedEvent($this->getFrwState(), $context));
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     */
    public function frwLogin(string $shopwareId, string $password, string $language, Context $context): void
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            throw new InvalidContextSourceException(AdminApiSource::class, \get_class($context->getSource()));
        }

        $response = $this->client->post(
            '/swplatform/firstrunwizard/login',
            [
                'json' => [
                    'shopwareId' => $shopwareId,
                    'password' => $password,
                ],
                'query' => $this->optionsProvider->getDefaultQueryParameters(null, $language),
            ]
        );

        $this->configService->set(self::SYSTEM_CONFIG_KEY_SHOPWARE_ID, $shopwareId);

        $data = \json_decode($response->getBody()->getContents(), true);

        $this->storeService->updateStoreToken(
            $context,
            $this->createAccessTokenStruct($data, $data['firstRunWizardUserToken'])
        );
    }

    public function upgradeAccessToken(string $language, Context $context): void
    {
        if (!$context->getSource() instanceof AdminApiSource
            || $context->getSource()->getUserId() === null) {
            throw new \RuntimeException('First run wizard requires a logged in user');
        }

        $response = $this->client->post(
            '/swplatform/login/upgrade',
            [
                'query' => $this->optionsProvider->getDefaultQueryParameters(null, $language),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
                'json' => [
                    'shopwareUserId' => $context->getSource()->getUserId(),
                ],
            ]
        );
        $data = \json_decode($response->getBody()->getContents(), true);

        $this->configService->set('core.store.shopSecret', $data['shopSecret']);

        $this->storeService->updateStoreToken(
            $context,
            $this->createAccessTokenStruct($data, $data['shopUserToken'])
        );
    }

    public function finishFrw(bool $failed, Context $context): void
    {
        $currentState = $this->getFrwState();

        if ($failed) {
            $newState = FrwState::failedState(null, $currentState->getFailureCount() + 1);
        } else {
            $this->fireTrackingEvent(self::TRACKING_EVENT_FRW_FINISHED);
            $newState = FrwState::completedState();
        }

        $this->setFrwStatus($newState);

        $this->eventDispatcher->dispatch(new FirstRunWizardFinishedEvent($newState, $currentState, $context));
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
     * @throws ClientException
     */
    public function getLanguagePlugins(string $language, PluginCollection $pluginCollection): array
    {
        return $this->mapPluginData(
            $this->getPluginsFromStore('/swplatform/firstrunwizard/localizations', $language),
            $pluginCollection
        );
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     */
    public function getDemoDataPlugins(string $language, PluginCollection $pluginCollection): array
    {
        return $this->mapPluginData(
            $this->getPluginsFromStore('/swplatform/firstrunwizard/demodataplugins', $language),
            $pluginCollection
        );
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     */
    public function getRecommendationRegions(string $language): PluginRegionCollection
    {
        $response = $this->client->get(
            '/swplatform/firstrunwizard/categories',
            ['query' => $this->optionsProvider->getDefaultQueryParameters(null, $language)]
        );
        $data = \json_decode($response->getBody()->getContents(), true);

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

    public function getRecommendations(string $language, PluginCollection $pluginCollection, ?string $region, ?string $category): PluginRecommendationCollection
    {
        $query = $this->optionsProvider->getDefaultQueryParameters(null, $language);
        $query['region'] = $query['market'] = $region;
        $query['category'] = $category;

        $response = $this->client->get(
            '/swplatform/firstrunwizard/plugins',
            ['query' => $query]
        );

        $data = \json_decode($response->getBody()->getContents(), true);

        return new PluginRecommendationCollection($this->mapPluginData($data, $pluginCollection));
    }

    public function getLicenseDomains(string $language, Context $context): LicenseDomainCollection
    {
        $response = $this->client->get(
            '/swplatform/firstrunwizard/shops',
            [
                'query' => $this->optionsProvider->getDefaultQueryParameters(null, $language),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );

        $data = \json_decode($response->getBody()->getContents(), true);

        $currentLicenseDomain = $this->configService->getString(StoreService::CONFIG_KEY_STORE_LICENSE_DOMAIN);
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

    public function verifyLicenseDomain(string $domain, string $language, Context $context, bool $testEnvironment = false): LicenseDomainStruct
    {
        $domains = $this->getLicenseDomains($language, $context);

        $existing = $domains->get($domain);
        if (!$existing || !$existing->isVerified()) {
            $secret = $this->fetchVerificationInfo($domain, $language, $context);
            $this->storeVerificationSecret($domain, $secret);
            $this->checkVerificationSecret($domain, $context, $testEnvironment);

            $domains = $this->getLicenseDomains($language, $context);
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

    private function createAccessTokenStruct(array $accessTokenData, array $userTokenData): AccessTokenStruct
    {
        $userToken = new ShopUserTokenStruct();
        $userToken->assign($userTokenData);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($accessTokenData);
        $accessTokenStruct->setShopUserToken($userToken);

        return $accessTokenStruct;
    }

    private function getPluginsFromStore(string $endpoint, string $language): array
    {
        $response = $this->client->get(
            $endpoint,
            ['query' => $this->optionsProvider->getDefaultQueryParameters(null, $language)]
        );

        return \json_decode($response->getBody()->getContents(), true);
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

    private function checkVerificationSecret(string $domain, Context $context, bool $testEnvironment): void
    {
        $this->client->post(
            '/swplatform/firstrunwizard/shops',
            [
                'json' => [
                    'domain' => $domain,
                    'shopwareVersion' => $this->instanceService->getShopwareVersion(),
                    'testEnvironment' => $testEnvironment,
                ],
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );
    }

    private function fetchVerificationInfo(string $domain, string $language, Context $context): DomainVerificationRequestStruct
    {
        $response = $this->client->post(
            '/swplatform/firstrunwizard/shopdomainverificationhash',
            [
                'json' => ['domain' => $domain],
                'query' => $this->optionsProvider->getDefaultQueryParameters(null, $language),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );
        $data = \json_decode($response->getBody()->getContents(), true);

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

    private function getFrwState(): FrwState
    {
        $completedAt = $this->configService->getString('core.frw.completedAt');
        if ($completedAt !== '') {
            return FrwState::completedState(new \DateTimeImmutable($completedAt));
        }
        $failedAt = $this->configService->getString('core.frw.failedAt');
        if ($failedAt !== '') {
            $failureCount = $this->configService->getInt('core.frw.failureCount');

            return FrwState::failedState(new \DateTimeImmutable($failedAt), $failureCount);
        }

        return FrwState::openState();
    }

    private function fireTrackingEvent(string $eventName): void
    {
        if (!$this->instanceService->getInstanceId()) {
            return;
        }

        try {
            $this->client->post(
                '/swplatform/tracking/events',
                [
                    'json' => [
                        'additionalData' => [
                            'shopwareVersion' => $this->instanceService->getShopwareVersion(),
                        ],
                        'instanceId' => $this->instanceService->getInstanceId(),
                        'event' => $eventName,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            // ignore exceptions
        }
    }
}
