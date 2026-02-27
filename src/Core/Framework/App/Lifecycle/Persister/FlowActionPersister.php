<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionCollection;
use Shopware\Core\Framework\App\Flow\Action\Action;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @codeCoverageIgnore @see \Shopware\Tests\Integration\Core\Framework\App\Lifecycle\FlowActionPersisterTest
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class FlowActionPersister implements PersisterInterface
{
    /**
     * @param EntityRepository<AppFlowActionCollection> $flowActionsRepository
     */
    public function __construct(
        private readonly EntityRepository $flowActionsRepository,
        private readonly Connection $connection
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $flowAction = $this->getFlowActions($context);

        if (!$flowAction) {
            return;
        }

        $existingFlowActions = $this->connection->fetchAllKeyValue('SELECT name, LOWER(HEX(id)) FROM app_flow_action WHERE app_id = :appId', [
            'appId' => Uuid::fromHexToBytes($context->app->getId()),
        ]);

        $flowActions = $flowAction->getActions()?->getActions() ?? [];
        $upserts = [];

        foreach ($flowActions as $action) {
            $icon = $action->getMeta()->getIcon();
            if ($icon && $context->appFilesystem->has('Resources/' . $icon)) {
                $icon = $context->appFilesystem->read('Resources/' . $icon);
            }

            $payload = array_merge([
                'appId' => $context->app->getId(),
                'iconRaw' => $icon,
            ], $action->toArray($context->defaultLocale));

            $existing = $existingFlowActions[$action->getMeta()->getName()] ?? null;
            if ($existing) {
                $payload['id'] = $existing;
                unset($existingFlowActions[$action->getMeta()->getName()]);
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $this->flowActionsRepository->upsert($upserts, $context->context);
        }

        $this->deleteOldAppFlowActions(\array_values($existingFlowActions), $context->context);
    }

    private function getFlowActions(AppLifecycleContext $context): ?Action
    {
        if (!$context->appFilesystem->has('Resources/flow.xml')) {
            return null;
        }

        return Action::createFromXmlFile($context->appFilesystem->path('Resources/flow.xml'));
    }

    /**
     * @param string[] $ids
     */
    private function deleteOldAppFlowActions(array $ids, Context $context): void
    {
        if ($ids === []) {
            return;
        }

        $ids = array_map(static fn (string $id): array => ['id' => $id], $ids);

        $this->flowActionsRepository->delete($ids, $context);
    }
}
