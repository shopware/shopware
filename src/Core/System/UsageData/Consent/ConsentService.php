<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Consent;

use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\UsageDataException;
use Shopware\Core\System\User\Aggregate\UserConfig\UserConfigEntity;

/**
 * @internal
 *
 * @phpstan-type AccessKeys array{accessKey: string, secretAccessKey: string}
 */
#[Package('merchant-services')]
class ConsentService
{
    public const SYSTEM_CONFIG_KEY_CONSENT_STATE = 'core.usageData.consentState';
    public const USER_CONFIG_KEY_HIDE_CONSENT_BANNER = 'core.usageData.hideConsentBanner';
    public const SYSTEM_CONFIG_KEY_INTEGRATION_ID = 'core.usageData.integrationId';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $userConfigRepository,
        private readonly EntityRepository $integrationRepository,
        private readonly ConsentReporter $consentReporter,
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

    public function hasConsentState(): bool
    {
        return $this->systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE) !== '';
    }

    public function isConsentAccepted(): bool
    {
        return $this->systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE) === ConsentState::ACCEPTED->value;
    }

    public function isConsentRevoked(): bool
    {
        return $this->systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE) === ConsentState::REVOKED->value;
    }

    public function hasUserHiddenConsentBanner(string $userId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('key', ConsentService::USER_CONFIG_KEY_HIDE_CONSENT_BANNER));
        $criteria->addFilter(new EqualsFilter('userId', $userId));

        /** @var UserConfigEntity|null $userConfig */
        $userConfig = $this->userConfigRepository->search($criteria, $context)->first();
        if ($userConfig === null) {
            return false;
        }

        return $userConfig->getValue()['_value'] ?? false;
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
            $this->consentReporter->report($consentState, $accessKeys);
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
            ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION_ID,
            $integrationId
        );

        return ['accessKey' => $accessKey, 'secretAccessKey' => $secretAccessKey];
    }

    private function deleteIntegration(): void
    {
        $integrationId = $this->systemConfigService->getString(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION_ID);

        if ($integrationId === '') {
            return;
        }

        try {
            $this->integrationRepository->delete([
                ['id' => $integrationId],
            ], Context::createDefaultContext());
        } catch (EntityNotFoundException) {
        }

        $this->systemConfigService->delete(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION_ID);
    }
}
