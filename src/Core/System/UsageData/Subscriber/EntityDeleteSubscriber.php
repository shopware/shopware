<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\EntityDeleteEventHelper;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('data-services')]
class EntityDeleteSubscriber implements EventSubscriberInterface
{
    public const DELETIONS_TABLE_NAME = 'usage_data_entity_deletion';

    public function __construct(
        private readonly EntityDefinitionService $entityDefinitionService,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        private readonly ConsentService $consentService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityDeleteEvent::class => 'handleEntityDeleteEvent',
        ];
    }

    public function handleEntityDeleteEvent(EntityDeleteEvent $event): void
    {
        if (!$this->consentService->isConsentAccepted()) {
            return;
        }

        $eventHelper = (new EntityDeleteEventHelper($event))
            ->forEntityDefinitions($this->entityDefinitionService->getAllowedEntityDefinitions())
            ->withExcludedFields([
                VersionField::class,
                ReferenceVersionField::class,
            ])
            ->prepare();

        $now = $this->clock->now();
        foreach ($eventHelper->getEntityIds() as $entityName => $entityIds) {
            $event->addSuccess(fn () => $this->storeDeletions(
                $entityName,
                $entityIds,
                $now,
            ));
        }
    }

    /**
     * @param list<array<string, string>> $primaryKeys
     */
    private function storeDeletions(string $entityName, array $primaryKeys, \DateTimeImmutable $now): void
    {
        try {
            $this->connection->beginTransaction();
            $statement = $this->connection->prepare($this->getInsertQuery()->getSQL());

            $formattedNow = $now->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            foreach ($primaryKeys as $primaryKey) {
                $statement->bindValue(':id', Uuid::randomBytes(), ParameterType::BINARY);
                $statement->bindValue(':entity_name', $entityName);
                $statement->bindValue(':entity_ids', \json_encode($primaryKey, \JSON_THROW_ON_ERROR));
                $statement->bindValue(':deleted_at', $formattedNow);

                $statement->executeStatement();
            }

            $this->connection->commit();
        } catch (DbalException) {
            $this->connection->rollBack();
        }
    }

    private function getInsertQuery(): QueryBuilder
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        $queryBuilder->insert(self::DELETIONS_TABLE_NAME);
        $queryBuilder->values([
            'id' => ':id',
            'entity_name' => ':entity_name',
            'entity_ids' => ':entity_ids',
            'deleted_at' => ':deleted_at',
        ]);

        return $queryBuilder;
    }
}
