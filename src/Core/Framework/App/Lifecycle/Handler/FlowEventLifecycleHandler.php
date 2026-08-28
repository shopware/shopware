<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventCollection;
use Shopware\Core\Framework\App\Flow\Event\Event;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class FlowEventLifecycleHandler extends AbstractLifecycleHandler
{
    /**
     * @param EntityRepository<AppFlowEventCollection> $flowEventsRepository
     */
    public function __construct(
        private readonly EntityRepository $flowEventsRepository,
        private readonly Connection $connection
    ) {
    }

    public function install(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function update(AppPersistContext $context): void
    {
        $this->persist($context);
    }

    public function deactivate(AppActivationContext $context): void
    {
        $this->connection->executeStatement(
            'UPDATE `flow` SET `active` = false WHERE `event_name` IN (SELECT `name` FROM `app_flow_event` WHERE `app_id` = :appId);',
            [
                'appId' => Uuid::fromHexToBytes($context->app->getId()),
            ],
        );
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

        if ($upserts !== []) {
            $this->flowEventsRepository->upsert($upserts, $context);
        }

        $this->deleteOldAppFlowEvents($existingFlowEvents, $context);
    }

    private function persist(AppPersistContext $context): void
    {
        $flowEvents = $this->getFlowEvents($context->appFilesystem);

        if ($flowEvents) {
            $this->updateEvents($flowEvents, $context->app->getId(), $context->context, $context->defaultLocale);
        }
    }

    private function getFlowEvents(Filesystem $fs): ?Event
    {
        if (!$fs->has('Resources/flow.xml')) {
            return null;
        }

        return Event::createFromXmlFile($fs->path('Resources/flow.xml'));
    }

    /**
     * @param array<int|string, mixed> $toBeRemoved
     */
    private function deleteOldAppFlowEvents(array $toBeRemoved, Context $context): void
    {
        $ids = array_values($toBeRemoved);

        if ($ids === []) {
            return;
        }

        $ids = array_map(static function (string $id): array {
            return ['id' => $id];
        }, $ids);

        $this->flowEventsRepository->delete($ids, $context);
    }
}
