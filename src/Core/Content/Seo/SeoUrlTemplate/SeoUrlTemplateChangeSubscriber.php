<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Automatically regenerates existing SEO URLs when an SEO URL template is changed
 * in Settings > Shop > SEO, so merchants (especially on SaaS) don't need to wait
 * for / trigger the SEO indexer manually.
 *
 * Update commands request a change set so re-submitting an identical template
 * (for example an idempotent Sync API call) does not trigger a reindex. Deletes
 * request one too, because removing a sales channel override changes the
 * effective template for that channel and the row is already gone once the write
 * succeeded. The actual regeneration is dispatched to the message bus after a
 * successful write, so iterating every product/category of the affected route
 * does not block the admin save.
 *
 * @internal
 */
#[Package('inventory')]
class SeoUrlTemplateChangeSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWriteEvent::class => 'onSeoUrlTemplateWrite',
        ];
    }

    public function onSeoUrlTemplateWrite(EntityWriteEvent $event): void
    {
        $commands = $event->getCommandsForEntity(SeoUrlTemplateDefinition::ENTITY_NAME);
        if ($commands === []) {
            return;
        }

        $insertedRoutes = [];
        $updateCommands = [];
        $deleteCommands = [];
        foreach ($commands as $command) {
            if ($command instanceof DeleteCommand) {
                // Removing a sales channel override changes the effective template for
                // that channel, so the URLs have to be regenerated as well. The row is
                // gone by the time the success callback runs, hence the change set: it
                // carries the deleted state we need to resolve the route.
                $command->requestChangeSet();
                $deleteCommands[] = $command;

                continue;
            }

            // Only re-index when the template itself is written; other field changes
            // (for example custom fields) must not trigger expensive reindexing.
            if (!$command->hasField('template')) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                $payload = $command->getPayload();
                $template = $payload['template'] ?? null;
                if (!\is_string($template) || $template === '') {
                    // An override without a template falls back to the default one, so
                    // there is nothing new to generate. This is what the admin sends for
                    // the per-sales-channel placeholder rows.
                    continue;
                }

                $insertedRoutes[] = [
                    'routeName' => (string) ($payload['route_name'] ?? ''),
                    'entityName' => (string) ($payload['entity_name'] ?? ''),
                ];

                continue;
            }

            if ($command instanceof UpdateCommand) {
                $command->requestChangeSet();
                $updateCommands[] = $command;
            }
        }

        if ($insertedRoutes === [] && $updateCommands === [] && $deleteCommands === []) {
            return;
        }

        $event->addSuccess(function () use ($insertedRoutes, $updateCommands, $deleteCommands): void {
            $this->dispatchIndexingMessages($insertedRoutes, $updateCommands, $deleteCommands);
        });
    }

    /**
     * @param list<array{routeName: string, entityName: string}> $insertedRoutes
     * @param list<UpdateCommand> $updateCommands
     * @param list<DeleteCommand> $deleteCommands
     */
    private function dispatchIndexingMessages(array $insertedRoutes, array $updateCommands, array $deleteCommands): void
    {
        $changedIds = [];
        foreach ($updateCommands as $command) {
            $changeSet = $command->getChangeSet();
            if ($changeSet !== null && !$changeSet->hasChanged('template')) {
                // The write submitted the same template again; nothing to regenerate.
                continue;
            }

            $primaryKey = $command->getDecodedPrimaryKey();
            $id = reset($primaryKey);
            if (\is_string($id)) {
                $changedIds[] = $id;
            }
        }

        $routes = [...$insertedRoutes, ...$this->resolveDeletedRoutes($deleteCommands)];
        if ($changedIds !== []) {
            $routes = [...$routes, ...$this->loadRoutesForTemplates($changedIds)];
        }

        $dispatched = [];
        foreach ($routes as $route) {
            if ($route['routeName'] === '' || $route['entityName'] === '') {
                continue;
            }

            $key = $route['routeName'] . '/' . $route['entityName'];
            if (isset($dispatched[$key])) {
                continue;
            }
            $dispatched[$key] = true;

            $this->messageBus->dispatch(
                new SeoUrlTemplateIndexingMessage($route['routeName'], $route['entityName'])
            );
        }
    }

    /**
     * @param list<DeleteCommand> $deleteCommands
     *
     * @return list<array{routeName: string, entityName: string}>
     */
    private function resolveDeletedRoutes(array $deleteCommands): array
    {
        $routes = [];
        foreach ($deleteCommands as $command) {
            $changeSet = $command->getChangeSet();
            if ($changeSet === null) {
                continue;
            }

            if ($changeSet->getBefore('sales_channel_id') === null) {
                // The route lost its default template, so there is nothing left to
                // generate from; SeoUrlUpdater would only fail on the missing default.
                continue;
            }

            $routes[] = [
                'routeName' => (string) $changeSet->getBefore('route_name'),
                'entityName' => (string) $changeSet->getBefore('entity_name'),
            ];
        }

        return $routes;
    }

    /**
     * @param list<string> $templateIds
     *
     * @return list<array{routeName: string, entityName: string}>
     */
    private function loadRoutesForTemplates(array $templateIds): array
    {
        $bytes = array_values(array_map(
            static fn (string $id): string => Uuid::fromHexToBytes($id),
            $templateIds
        ));

        /** @var list<array{routeName: string, entityName: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT route_name AS routeName, entity_name AS entityName
             FROM seo_url_template
             WHERE id IN (:ids)',
            ['ids' => $bytes],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $rows;
    }
}
