<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class FlowActionPersister
{
    private EntityRepository $flowActionsRepository;

    private AbstractAppLoader $appLoader;

    private Connection $connection;

    public function __construct(
        EntityRepository $flowActionsRepository,
        AbstractAppLoader $appLoader,
        Connection $connection
    ) {
        $this->flowActionsRepository = $flowActionsRepository;
        $this->appLoader = $appLoader;
        $this->connection = $connection;
    }

    public function updateActions(FlowAction $flowAction, string $appId, Context $context, string $defaultLocale): void
    {
        $existingFlowActions = $this->connection->fetchAllKeyValue('SELECT name, LOWER(HEX(id)) FROM app_flow_action WHERE app_id = :appId', [
            'appId' => Uuid::fromHexToBytes($appId),
        ]);

        $flowActions = $flowAction->getActions() ? $flowAction->getActions()->getActions() : [];
        $upserts = [];

        foreach ($flowActions as $action) {
            $payload = array_merge([
                'appId' => $appId,
                'iconRaw' => $this->appLoader->getFlowActionIcon($action->getMeta()->getIcon(), $flowAction),
            ], $action->toArray($defaultLocale));

            $existing = $existingFlowActions[$action->getMeta()->getName()] ?? null;
            if ($existing) {
                $payload['id'] = $existing;
                unset($existingFlowActions[$action->getMeta()->getName()]);
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $this->flowActionsRepository->upsert($upserts, $context);
        }

        $this->deleteOldAppFlowActions($existingFlowActions, $context);
    }

    private function deleteOldAppFlowActions(array $toBeRemoved, Context $context): void
    {
        $ids = array_values($toBeRemoved);

        if (empty($ids)) {
            return;
        }

        $ids = array_map(static function (string $id): array {
            return ['id' => $id];
        }, array_values($ids));

        $this->flowActionsRepository->delete($ids, $context);
    }
}
