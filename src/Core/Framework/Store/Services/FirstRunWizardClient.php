<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
use function json_decode;

/**
 * @package merchant-services
 *
 * @internal
 */
final class FirstRunWizardClient
{
    public const USER_CONFIG_KEY_FRW_USER_TOKEN = 'core.frw.userToken';
    public const USER_CONFIG_VALUE_FRW_USER_TOKEN = 'frwUserToken';

    private const TRACKING_EVENT_FRW_STARTED = 'First Run Wizard started';
    private const TRACKING_EVENT_FRW_FINISHED = 'First Run Wizard finished';

    private const FRW_MAX_FAILURES = 3;

    private Client $client;

    private StoreService $storeService;

    private SystemConfigService $configService;

    private FilesystemOperator $filesystem;

    private bool $frwAutoRun;

    private EventDispatcherInterface $eventDispatcher;

    private AbstractStoreRequestOptionsProvider $optionsProvider;

    private InstanceService $instanceService;

    private EntityRepository $userConfigRepository;

    private TrackingEventClient $trackingEventClient;

    public function __construct(
        StoreService $storeService,
        SystemConfigService $configService,
        FilesystemOperator $filesystem,
        bool $frwAutoRun,
        EventDispatcherInterface $eventDispatcher,
        Client $client,
        AbstractStoreRequestOptionsProvider $optionsProvider,
        InstanceService $instanceService,
        EntityRepository $userConfigRepository,
        TrackingEventClient $trackingEventClient
    ) {
        $this->storeService = $storeService;
        $this->client = $client;
        $this->optionsProvider = $optionsProvider;
        $this->instanceService = $instanceService;
        $this->configService = $configService;
        $this->filesystem = $filesystem;
        $this->frwAutoRun = $frwAutoRun;
        $this->eventDispatcher = $eventDispatcher;
        $this->userConfigRepository = $userConfigRepository;
        $this->trackingEventClient = $trackingEventClient;
    }

    public function startFrw(Context $context): void
    {
        $this->trackingEventClient->fireTrackingEvent(self::TRACKING_EVENT_FRW_STARTED);

        $this->eventDispatcher->dispatch(new FirstRunWizardStartedEvent($this->getFrwState(), $context));
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     */
    public function frwLogin(string $shopwareId, string $password, Context $context): void
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
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        $this->updateFrwUserToken(
            $context,
            $this->createAccessTokenStruct($data, $data['firstRunWizardUserToken'])
        );
    }

    public function upgradeAccessToken(Context $context): void
    {
        if (!$context->getSource() instanceof AdminApiSource
            || $context->getSource()->getUserId() === null) {
            throw new \RuntimeException('First run wizard requires a logged in user');
        }

        $response = $this->client->post(
            '/swplatform/login/upgrade',
            [
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
                'json' => [
                    'shopwareUserId' => $context->getSource()->getUserId(),
                ],
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        $this->configService->set('core.store.shopSecret', $data['shopSecret']);

        $this->storeService->updateStoreToken(
            $context,
            $this->createAccessTokenStruct($data, $data['shopUserToken'])
        );

        $this->removeFrwUserToken($context);
    }

    public function finishFrw(bool $failed, Context $context): void
    {
        $currentState = $this->getFrwState();

        if ($failed) {
            $newState = FrwState::failedState(null, $currentState->getFailureCount() + 1);
        } else {
            $this->trackingEventClient->fireTrackingEvent(self::TRACKING_EVENT_FRW_FINISHED);
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
     *
     * @return StorePluginStruct[]
     */
    public function getLanguagePlugins(PluginCollection $pluginCollection, Context $context): array
    {
        return $this->mapPluginData(
            $this->getPluginsFromStore('/swplatform/firstrunwizard/localizations', $context),
            $pluginCollection
        );
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     *
     * @return StorePluginStruct[]
     */
    public function getDemoDataPlugins(PluginCollection $pluginCollection, Context $context): array
    {
        return $this->mapPluginData(
            $this->getPluginsFromStore('/swplatform/firstrunwizard/demodataplugins', $context),
            $pluginCollection
        );
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     */
    public function getRecommendationRegions(Context $context): PluginRegionCollection
    {
        $response = $this->client->get(
            '/swplatform/firstrunwizard/categories',
            ['query' => $this->optionsProvider->getDefaultQueryParameters($context)]
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

    public function getRecommendations(
        PluginCollection $pluginCollection,
        ?string $region,
        ?string $category,
        Context $context
    ): PluginRecommendationCollection {
        $query = $this->optionsProvider->getDefaultQueryParameters($context);
        $query['region'] = $query['market'] = $region;
        $query['category'] = $category;

        $response = $this->client->get(
            '/swplatform/firstrunwizard/plugins',
            ['query' => $query]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        return new PluginRecommendationCollection($this->mapPluginData($data, $pluginCollection));
    }

    public function getLicenseDomains(Context $context): LicenseDomainCollection
    {
        $response = $this->client->get(
            '/swplatform/firstrunwizard/shops',
            [
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

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

    public function verifyLicenseDomain(string $domain, Context $context, bool $testEnvironment = false): LicenseDomainStruct
    {
        $domains = $this->getLicenseDomains($context);

        $existing = $domains->get($domain);
        if (!$existing || !$existing->isVerified()) {
            $secret = $this->fetchVerificationInfo($domain, $context);
            $this->storeVerificationSecret($domain, $secret);
            $this->checkVerificationSecret($domain, $context, $testEnvironment);

            $domains = $this->getLicenseDomains($context);
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

    /**
     * @param array<mixed> $accessTokenData
     * @param array<mixed> $userTokenData
     */
    private function createAccessTokenStruct(array $accessTokenData, array $userTokenData): AccessTokenStruct
    {
        $userToken = new ShopUserTokenStruct();
        $userToken->assign($userTokenData);

        $accessTokenStruct = new AccessTokenStruct();
        $accessTokenStruct->assign($accessTokenData);
        $accessTokenStruct->setShopUserToken($userToken);

        return $accessTokenStruct;
    }

    /**
     * @return array<mixed>
     */
    private function getPluginsFromStore(string $endpoint, Context $context): array
    {
        $response = $this->client->get(
            $endpoint,
            ['query' => $this->optionsProvider->getDefaultQueryParameters($context)]
        );

        return json_decode($response->getBody()->getContents(), true);
    }

    private function setFrwStatus(FrwState $newState): void
    {
        $currentState = $this->getFrwState();
        $completedAt = null;
        $failedAt = null;
        $failureCount = null;

        if ($newState->isCompleted() && $newState->getCompletedAt()) {
            $completedAt = $newState->getCompletedAt()->format(\DateTimeImmutable::ATOM);
        } elseif ($newState->isFailed() && $newState->getFailedAt()) {
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

    private function fetchVerificationInfo(string $domain, Context $context): DomainVerificationRequestStruct
    {
        $response = $this->client->post(
            '/swplatform/firstrunwizard/shopdomainverificationhash',
            [
                'json' => ['domain' => $domain],
                'query' => $this->optionsProvider->getDefaultQueryParameters($context),
                'headers' => $this->optionsProvider->getAuthenticationHeader($context),
            ]
        );
        $data = json_decode($response->getBody()->getContents(), true);

        return new DomainVerificationRequestStruct($data['content'], $data['fileName']);
    }

    /**
     * @param array<string, mixed> $plugins
     *
     * @return StorePluginStruct[]
     */
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
            $this->filesystem->write($validationRequest->getFileName(), $validationRequest->getContent());
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

    private function updateFrwUserToken(Context $context, AccessTokenStruct $accessToken): void
    {
        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();
        $userId = $contextSource->getUserId();

        $frwUserToken = $accessToken->getShopUserToken()->getToken();
        $id = $this->getFrwUserTokenConfigId($context);

        $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($userId, $frwUserToken, $id): void {
            $this->userConfigRepository->upsert(
                [
                    [
                        'id' => $id,
                        'userId' => $userId,
                        'key' => self::USER_CONFIG_KEY_FRW_USER_TOKEN,
                        'value' => [self::USER_CONFIG_VALUE_FRW_USER_TOKEN => $frwUserToken,
                        ],
                    ],
                ],
                $context
            );
        });
    }

    private function removeFrwUserToken(Context $context): void
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return;
        }

        $id = $this->getFrwUserTokenConfigId($context);

        if ($id) {
            $context->scope(Context::SYSTEM_SCOPE, function ($context) use ($id): void {
                $this->userConfigRepository->delete([['id' => $id]], $context);
            });
        }
    }

    private function getFrwUserTokenConfigId(Context $context): ?string
    {
        if (!$context->getSource() instanceof AdminApiSource) {
            return null;
        }

        /** @var AdminApiSource $contextSource */
        $contextSource = $context->getSource();

        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('userId', $contextSource->getUserId()),
            new EqualsFilter('key', self::USER_CONFIG_KEY_FRW_USER_TOKEN)
        );

        return $this->userConfigRepository->searchIds($criteria, $context)->firstId();
    }
}
