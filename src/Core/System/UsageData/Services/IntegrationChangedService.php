<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;

/**
 * @internal
 *
 * @phpstan-import-type SystemConfigIntegration from ConsentService
 */
#[Package('merchant-services')]
class IntegrationChangedService
{
    public function __construct(
        private readonly EntityRepository $integrationRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConsentService $consentService,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly EntityDispatchService $entityDispatchService,
    ) {
    }

    public function checkAndHandleIntegrationChanged(): void
    {
        if (!$this->consentService->isConsentAccepted()) {
            return;
        }

        /** @var SystemConfigIntegration|null $systemConfigIntegration */
        $systemConfigIntegration = $this->systemConfigService->get(ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION);

        if ($systemConfigIntegration === null) {
            return;
        }

        $this->checkAndHandleIntegrationAppUrlChanged($systemConfigIntegration);
        $this->checkAndHandleIntegrationShopIdChanged($systemConfigIntegration);
    }

    /**
     * @param SystemConfigIntegration $systemConfigIntegration
     */
    private function checkAndHandleIntegrationAppUrlChanged(array $systemConfigIntegration): void
    {
        /** @var string $newAppUrl */
        $newAppUrl = EnvironmentHelper::getVariable('APP_URL');
        if ($systemConfigIntegration['appUrl'] === $newAppUrl) {
            return;
        }

        if ($systemConfigIntegration['integrationId'] === null) {
            return;
        }

        $integrationSearchResult = $this->integrationRepository->search(
            new Criteria([$systemConfigIntegration['integrationId']]),
            Context::createDefaultContext()
        );

        /** @var IntegrationEntity|null $integration */
        $integration = $integrationSearchResult->first();

        if ($integration === null) {
            return;
        }

        $this->reportAndSetNewIntegrationAppUrl($integration, $newAppUrl);
    }

    /**
     * @param SystemConfigIntegration $systemConfigIntegration
     */
    private function checkAndHandleIntegrationShopIdChanged(array $systemConfigIntegration): void
    {
        $newShopId = $this->shopIdProvider->getShopId();

        if ($systemConfigIntegration['shopId'] === $newShopId) {
            return;
        }

        $this->resetUsageDataState();
    }

    private function resetUsageDataState(): void
    {
        // revoke consent and delete consent state
        $this->consentService->revokeConsent();
        $this->systemConfigService->delete(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE);

        $this->entityDispatchService->resetLastRunDateForAllEntities();

        // enable data push again
        $this->systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_DATA_PUSH_DISABLED, false);

        $this->consentService->resetIsBannerHiddenToFalseForAllUsers();
    }

    private function reportAndSetNewIntegrationAppUrl(IntegrationEntity $integration, string $newAppUrl): void
    {
        $shopId = $this->shopIdProvider->getShopId();

        $this->consentService->updateConsentIntegrationAppUrl(
            $shopId,
            [
                'accessKey' => $integration->getAccessKey(),
                'secretAccessKey' => $integration->getSecretAccessKey(),
                'appUrl' => $newAppUrl,
            ],
        );

        $this->systemConfigService->set(
            ConsentService::SYSTEM_CONFIG_KEY_INTEGRATION,
            [
                'integrationId' => $integration->getId(),
                'appUrl' => $newAppUrl,
                'shopId' => $shopId,
            ]
        );
    }
}
