<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\UsageDataException;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @internal
 *
 * @phpstan-type AccessKeys array{accessKey: string, secretAccessKey: string, appUrl: string}
 * @phpstan-type SystemConfigIntegration array{integrationId: string|null, appUrl: string, shopId: string}
 */
#[Package('merchant-services')]
class ConsentService
{
    public const SYSTEM_CONFIG_KEY_CONSENT_STATE = 'core.usageData.consentState';
    public const USER_CONFIG_KEY_HIDE_CONSENT_BANNER = 'core.usageData.hideConsentBanner';
    public const SYSTEM_CONFIG_KEY_INTEGRATION = 'core.usageData.integration';
    public const SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED = 'core.usageData.dataPushDisabled';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $systemConfigRepository,
        private readonly EntityRepository $userConfigRepository,
        private readonly EntityRepository $integrationRepository,
        private readonly ConsentReporter $consentReporter,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly ClockInterface $clock,
        private readonly string $appUrl,
    ) {
    }

    public function requestConsent(): void
    {
        if ($this->hasConsentState()) {
            throw UsageDataException::consentAlreadyRequested();
        }

        $this->storeAndReportConsentState(ConsentState::REQUESTED);
    }

    public function acceptConsent(): void
    {
        if ($this->isConsentAccepted()) {
            throw UsageDataException::consentAlreadyAccepted();
        }

        $accessKeys = $this->createIntegration();

        $this->storeAndReportConsentState(ConsentState::ACCEPTED, $accessKeys);
    }

    public function revokeConsent(): void
    {
        if ($this->isConsentRevoked()) {
            throw UsageDataException::consentAlreadyRevoked();
        }

        $this->deleteIntegration();

        $this->storeAndReportConsentState(ConsentState::REVOKED);
    }

    /**
     * @param AccessKeys $accessKeys
     */
    public function updateConsentIntegrationAppUrl(string $shopId, array $accessKeys): void
    {
        $this->consentReporter->reportConsentIntegrationAppUrlChanged($shopId, $accessKeys);
    }

    /**
     * Returns the last date when we still had the consent.
     * If we never had the consent before, null is returned.
     */
    public function getLastConsentIsAcceptedDate(): ?\DateTimeInterface
    {
        if ($this->isConsentAccepted()) {
            return $this->clock->now();
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('configurationKey', self::SYSTEM_CONFIG_KEY_CONSENT_STATE));
        $criteria->setLimit(1);
        $entitySearchResult = $this->systemConfigRepository->search($criteria, Context::createDefaultContext());
        $config = $entitySearchResult->first();

        return $config?->getUpdatedAt();
    }

    public function hasConsentState(): bool
    {
        return $this->systemConfigService->getString(self::SYSTEM_CONFIG_KEY_CONSENT_STATE) !== '';
    }

    public function isConsentAccepted(): bool
    {
        return $this->systemConfigService->getString(self::SYSTEM_CONFIG_KEY_CONSENT_STATE) === ConsentState::ACCEPTED->value;
    }

    public function isConsentRevoked(): bool
    {
        return $this->systemConfigService->getString(self::SYSTEM_CONFIG_KEY_CONSENT_STATE) === ConsentState::REVOKED->value;
    }

    public function shouldPushData(): bool
    {
        return !$this->systemConfigService->getBool(self::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED);
    }

    public function hideConsentBannerForUser(string $userId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('userId', $userId));
        $criteria->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigId = $this->userConfigRepository->searchIds($criteria, $context)->firstId();

        $this->userConfigRepository->upsert([
            [
                'id' => $userConfigId ?: Uuid::randomHex(),
                'userId' => $userId,
                'key' => self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
                'value' => ['_value' => true],
            ],
        ], $context);
    }

    public function hasUserHiddenConsentBanner(string $userId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));
        $criteria->addFilter(new EqualsFilter('userId', $userId));

        /** @var UserConfigEntity|null $userConfig */
        $userConfig = $this->userConfigRepository->search($criteria, $context)->first();
        if ($userConfig === null) {
            return false;
        }

        return $userConfig->getValue()['_value'] ?? false;
    }

    public function resetIsBannerHiddenToFalseForAllUsers(): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));

        $userConfigs = $this->userConfigRepository->search($criteria, Context::createDefaultContext());
        if ($userConfigs->getTotal() === 0) {
            return;
        }

        $updates = [];

        /** @var UserConfigEntity $userConfig */
        foreach ($userConfigs->getElements() as $userConfig) {
            $updates[] = [
                'id' => $userConfig->getId(),
                'userId' => $userConfig->getUserId(),
                'key' => self::USER_CONFIG_KEY_HIDE_CONSENT_BANNER,
                'value' => ['_value' => false],
            ];
        }

        $this->userConfigRepository->upsert($updates, Context::createDefaultContext());
    }

    /**
     * @param AccessKeys|null $accessKeys
     */
    private function storeAndReportConsentState(ConsentState $consentState, ?array $accessKeys = null): void
    {
        $this->systemConfigService->set(
            self::SYSTEM_CONFIG_KEY_CONSENT_STATE,
            $consentState->value,
        );

        try {
            $this->consentReporter->reportConsent($consentState, $accessKeys);
        } catch (\Throwable) {
        }
    }

    /**
     * @return AccessKeys
     */
    private function createIntegration(): array
    {
        $this->integrationRepository->create([
            [
                'id' => $integrationId = Uuid::randomHex(),
                'writeAccess' => true,
                'accessKey' => $accessKey = AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => $secretAccessKey = AccessKeyHelper::generateSecretAccessKey(),
                'label' => 'Data sharing with shopware AG',
                'admin' => true,
            ],
        ], Context::createDefaultContext());

        $this->systemConfigService->set(
            self::SYSTEM_CONFIG_KEY_INTEGRATION,
            [
                'integrationId' => $integrationId,
                'appUrl' => $this->appUrl,
                'shopId' => $this->shopIdProvider->getShopId(),
            ],
        );

        return [
            'accessKey' => $accessKey,
            'secretAccessKey' => $secretAccessKey,
            'appUrl' => $this->appUrl,
        ];
    }

    private function deleteIntegration(): void
    {
        /** @var SystemConfigIntegration|null $integration */
        $integration = $this->systemConfigService->get(self::SYSTEM_CONFIG_KEY_INTEGRATION);

        if (!\is_array($integration)) {
            return;
        }

        try {
            $this->integrationRepository->delete([
                ['id' => $integration['integrationId']],
            ], Context::createDefaultContext());
        } catch (EntityNotFoundException) {
        }

        $integration['integrationId'] = null;
        $this->systemConfigService->set(
            self::SYSTEM_CONFIG_KEY_INTEGRATION,
            $integration,
        );
    }
}
