<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\EntitySync\CollectEntityDataMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('data-services')]
class EntityDispatchService
{
    private const LAST_RUN_CONFIG_KEY = 'usageData-entitySync-lastRun';

    private const SYSTEM_CONFIG_KEY_LAST_ENTITY_SYNC_RUN = 'core.usageData.lastEntitySyncRunDate';

    private const MIN_LAST_ENTITY_SYNC_DIFFERENCE = 60 * 60 * 12; // 12 hours

    public function __construct(
        private readonly EntityDefinitionService $entityDefinitionService,
        private readonly AbstractKeyValueStorage $appConfig,
        private readonly MessageBusInterface $messageBus,
        private readonly GatewayStatusService $gatewayStatusService,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly ConsentService $consentService,
        private readonly bool $collectionEnabled,
    ) {
    }

    public static function getLastRunKeyForEntity(string $entityName): string
    {
        return \sprintf('%s-%s', self::LAST_RUN_CONFIG_KEY, $entityName);
    }

    public function dispatchCollectEntityDataMessage(): void
    {
        if (!$this->collectionEnabled) {
            return;
        }

        if ($this->consentService->getConsentState(BackendData::NAME, Context::createDefaultContext())->acceptedUntil === null) {
            return;
        }

        $this->messageBus->dispatch(new CollectEntityDataMessage($this->shopIdProvider->getShopId()));
    }

    public function dispatchIterateEntityMessages(CollectEntityDataMessage $message): void
    {
        $backendDataConsent = $this->consentService->getConsentState(BackendData::NAME, Context::createDefaultContext());
        if ($backendDataConsent->acceptedUntil === null) {
            return;
        }

        $runDate = new \DateTimeImmutable($backendDataConsent->acceptedUntil);
        if (!$this->hasMinimumTimeElapsed($runDate)) {
            return;
        }

        // don't start iterating if shopId is different; handle old messages without shopId
        if ($message->shopId !== null && $this->shopIdProvider->getShopId() !== $message->shopId) {
            return;
        }

        if (!$this->gatewayStatusService->isGatewayAllowsPush()) {
            return;
        }

        foreach ($this->entityDefinitionService->getAllowedEntityDefinitions() as $entityDefinition) {
            $entityName = $entityDefinition->getEntityName();
            $lastRun = $this->getLastRun($entityName);

            $operationsToDispatch = $this->getOperationsToDispatch($lastRun === null);

            foreach ($operationsToDispatch as $operation) {
                // dispatch messages for current shopId if it is an old message without shopId
                $this->messageBus->dispatch(new IterateEntityMessage(
                    $entityName,
                    $operation,
                    $runDate,
                    $lastRun,
                    $message->shopId ?? $this->shopIdProvider->getShopId()
                ));
            }

            $this->appConfig->set(
                self::getLastRunKeyForEntity($entityName),
                $runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            );
        }

        $this->systemConfigService->set(self::SYSTEM_CONFIG_KEY_LAST_ENTITY_SYNC_RUN, $runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), null, true);
    }

    public function resetLastRunDateForAllEntities(): void
    {
        foreach ($this->entityDefinitionService->getAllowedEntityDefinitions() as $entityDefinition) {
            $this->appConfig->remove(self::getLastRunKeyForEntity($entityDefinition->getEntityName()));
        }
    }

    private function getLastRun(string $entityName): ?\DateTimeImmutable
    {
        $lastRunDate = $this->appConfig->get(self::getLastRunKeyForEntity($entityName));

        return $lastRunDate === null ? null : (new \DateTimeImmutable($lastRunDate));
    }

    /**
     * @return array<Operation::*>
     */
    private function getOperationsToDispatch(bool $isFirstRun): array
    {
        if ($isFirstRun) {
            return [Operation::CREATE];
        }

        return Operation::cases();
    }

    private function hasMinimumTimeElapsed(\DateTimeImmutable $lastCollectionAllowedDate): bool
    {
        $lastRunDate = $this->systemConfigService->get(self::SYSTEM_CONFIG_KEY_LAST_ENTITY_SYNC_RUN);
        if (!\is_string($lastRunDate)) {
            return true;
        }
        $lastRunDate = new \DateTimeImmutable($lastRunDate);

        return $lastCollectionAllowedDate->getTimestamp() > ($lastRunDate->getTimestamp() + self::MIN_LAST_ENTITY_SYNC_DIFFERENCE);
    }
}
