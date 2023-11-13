<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Services;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\EntitySync\CollectEntityDataMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('merchant-services')]
class EntityDispatchService
{
    private const LAST_RUN_CONFIG_KEY = 'usageData-entitySync-lastRun';

    public function __construct(
        private readonly EntityDefinitionService $entityDefinitionService,
        private readonly AbstractKeyValueStorage $appConfig,
        private readonly MessageBusInterface $messageBus,
        private readonly ClockInterface $clock,
        private readonly ConsentService $consentService,
        private readonly GatewayStatusService $gatewayStatusService,
    ) {
    }

    public static function getLastRunKeyForEntity(string $entityName): string
    {
        return sprintf('%s-%s', self::LAST_RUN_CONFIG_KEY, $entityName);
    }

    public function dispatchCollectEntityDataMessage(): void
    {
        $this->messageBus->dispatch(new CollectEntityDataMessage());
    }

    public function dispatchIterateEntityMessages(): void
    {
        if (!$this->consentService->isConsentAccepted()) {
            return;
        }

        if (!$this->consentService->shouldPushData()) {
            return;
        }

        if (!$this->gatewayStatusService->isGatewayAllowsPush()) {
            return;
        }

        // TODO: Do not run multiple times i.e. return if already started
        $runDate = $this->clock->now();

        foreach ($this->entityDefinitionService->getAllowedEntityDefinitions() as $entityDefinition) {
            $entityName = $entityDefinition->getEntityName();
            $lastRun = $this->getLastRun($entityName);

            $operationsToDispatch = $this->getOperationsToDispatch($lastRun === null);

            foreach ($operationsToDispatch as $operation) {
                $this->messageBus->dispatch(new IterateEntityMessage($entityName, $operation, $runDate, $lastRun));
            }

            $this->appConfig->set(
                self::getLastRunKeyForEntity($entityName),
                $runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)
            );
        }
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
}
