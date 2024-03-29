<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class FlowEventPersister
{
    public function __construct(
        private readonly EntityRepository $flowEventsRepository,
        private readonly Connection $connection
    ) {
    }

    public function updateEvents(Event $flowEvent, string $appId, Context $context, string $defaultLocale): void
    {
        $existingFlowEvents = $this->connection->fetchAllKeyValue('SELECT name, LOWER(HEX(id)) FROM app_flow_event WHERE app_id = :appId;', [
            'appId' => Uuid::fromHexToBytes($appId),
        ]);

        $flowEvents = $flowEvent->getCustomEvents()?->getCustomEvents() ?? [];
        $upserts = [];
        foreach ($flowEvents as $event) {
            $payload = array_merge([
                'appId' => $appId,
            ], $event->toArray($defaultLocale));

            $existing = $existingFlowEvents[$event->getName()] ?? null;
            if ($existing) {
                $payload['id'] = $existing;
                unset($existingFlowEvents[$event->getName()]);
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $this->flowEventsRepository->upsert($upserts, $context);
        }

        $this->deleteOldAppFlowEvents($existingFlowEvents, $context);
    }

    public function deactivateFlow(string $appId): void
    {
        $this->connection->executeStatement(
            'UPDATE `flow` SET `active` = false WHERE `event_name` IN (SELECT `name` FROM `app_flow_event` WHERE `app_id` = :appId);',
            [
                'appId' => Uuid::fromHexToBytes($appId),
            ],
        );
    }

    /**
     * @param array<int|string, mixed> $toBeRemoved
     */
    private function deleteOldAppFlowEvents(array $toBeRemoved, Context $context): void
    {
        $ids = array_values($toBeRemoved);

        if (empty($ids)) {
            return;
        }

        $ids = array_map(static function (string $id): array {
            return ['id' => $id];
        }, $ids);

        $this->flowEventsRepository->delete($ids, $context);
    }
}
