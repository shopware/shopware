<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\FlowAction\FlowAction;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLoader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('core')]
class FlowActionPersister
{
    public function __construct(
        private readonly EntityRepository $flowActionsRepository,
        private readonly AbstractAppLoader $appLoader,
        private readonly Connection $connection
    ) {
    }

    public function updateActions(FlowAction $flowAction, string $appId, Context $context, string $defaultLocale): void
    {
        /** @var array<string, string> $existingFlowActions */
        $existingFlowActions = $this->connection->fetchAllKeyValue('SELECT name, LOWER(HEX(id)) FROM app_flow_action WHERE app_id = :appId', [
            'appId' => Uuid::fromHexToBytes($appId),
        ]);

        $flowActions = $flowAction->getActions() ? $flowAction->getActions()->getActions() : [];
        $upserts = [];

        foreach ($flowActions as $action) {
            if ($icon = $action->getMeta()->getIcon()) {
                $icon = $this->appLoader->loadFile($flowAction->getPath(), $icon);
            }

            $payload = array_merge([
                'appId' => $appId,
                'iconRaw' => $icon,
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

        $this->deleteOldAppFlowActions(\array_values($existingFlowActions), $context);
    }

    /**
     * @param string[] $ids
     */
    private function deleteOldAppFlowActions(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $ids = array_map(static fn (string $id): array => ['id' => $id], $ids);

        $this->flowActionsRepository->delete($ids, $context);
    }
}
