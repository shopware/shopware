<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Services;

use GuzzleHttp\Exception\ClientException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
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
 *
 * @final
 */
#[Package('merchant-services')]
class FirstRunWizardService
{
    final public const USER_CONFIG_KEY_FRW_USER_TOKEN = 'core.frw.userToken';
    final public const USER_CONFIG_VALUE_FRW_USER_TOKEN = 'frwUserToken';

    private const TRACKING_EVENT_FRW_STARTED = 'First Run Wizard started';
    private const TRACKING_EVENT_FRW_FINISHED = 'First Run Wizard finished';

    private const FRW_MAX_FAILURES = 3;

    public function __construct(
        private readonly StoreService $storeService,
        private readonly SystemConfigService $configService,
        private readonly FilesystemOperator $filesystem,
        private readonly bool $frwAutoRun,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FirstRunWizardClient $frwClient,
        private readonly EntityRepository $userConfigRepository,
        private readonly TrackingEventClient $trackingEventClient
    ) {
    }

    public function startFrw(Context $context): void
    {
        $this->trackingEventClient->fireTrackingEvent(self::TRACKING_EVENT_FRW_STARTED);

        $this->eventDispatcher->dispatch(new FirstRunWizardStartedEvent($this->getFrwState(), $context));
    }

    public function frwLogin(string $shopwareId, string $password, Context $context): void
    {
        $accessTokenResponse = $this->frwClient->frwLogin($shopwareId, $password, $context);
        $accessToken = $this->createAccessTokenStruct($accessTokenResponse, $accessTokenResponse['firstRunWizardUserToken']);

        $this->updateFrwUserToken($context, $accessToken);
    }

    public function upgradeAccessToken(Context $context): void
    {
        $accessTokenResponse = $this->frwClient->upgradeAccessToken($context);
        $accessToken = $this->createAccessTokenStruct($accessTokenResponse, $accessTokenResponse['shopUserToken']);

        $this->storeService->updateStoreToken($context, $accessToken);
        $this->configService->set(StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET, $accessToken->getShopSecret());
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
        $languagePlugins = $this->frwClient->getLanguagePlugins($context);

        return $this->mapPluginData($languagePlugins, $pluginCollection);
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     *
     * @return StorePluginStruct[]
     */
    public function getDemoDataPlugins(PluginCollection $pluginCollection, Context $context): array
    {
        $demodataPlugins = $this->frwClient->getDemoDataPlugins($context);

        return $this->mapPluginData($demodataPlugins, $pluginCollection);
    }

    /**
     * @throws StoreLicenseDomainMissingException
     * @throws ClientException
     */
    public function getRecommendationRegions(Context $context): PluginRegionCollection
    {
        $regions = new PluginRegionCollection();

        foreach ($this->frwClient->getRecommendationRegions($context) as $region) {
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
        $recommendations = $this->frwClient->getRecommendations($region, $category, $context);

        return new PluginRecommendationCollection($this->mapPluginData($recommendations, $pluginCollection));
    }

    public function getLicenseDomains(Context $context): LicenseDomainCollection
    {
        $licenseDomains = $this->frwClient->getLicenseDomains($context);

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
        }, $licenseDomains);

        return new LicenseDomainCollection($domains);
    }

    public function verifyLicenseDomain(string $domain, Context $context, bool $testEnvironment = false): LicenseDomainStruct
    {
        $domains = $this->getLicenseDomains($context);

        $existing = $domains->get($domain);
        if (!$existing || !$existing->isVerified()) {
            $secretResponse = $this->frwClient->fetchVerificationInfo($domain, $context);
            $secret = new DomainVerificationRequestStruct($secretResponse['content'], $secretResponse['fileName']);
            $this->storeVerificationSecret($domain, $secret);
            $this->frwClient->checkVerificationSecret($domain, $context, $testEnvironment);

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
        } catch (UnableToWriteFile) {
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

    /**
     * @param array{shopSecret?: string} $accessTokenData
     * @param array{token: string, expirationDate: string} $userTokenData
     */
    private function createAccessTokenStruct(array $accessTokenData, array $userTokenData): AccessTokenStruct
    {
        $userToken = new ShopUserTokenStruct(
            $userTokenData['token'],
            new \DateTimeImmutable($userTokenData['expirationDate'])
        );

        return new AccessTokenStruct(
            $userToken,
            $accessTokenData['shopSecret'] ?? null,
        );
    }
}
