<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
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
        foreach ($commands as $command) {
            if (!$command->hasField('template')) {
                continue;
            }

            if ($command instanceof InsertCommand) {
                $payload = $command->getPayload();
                $template = $payload['template'] ?? null;
                if (!\is_string($template) || $template === '') {
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

        if ($insertedRoutes === [] && $updateCommands === []) {
            return;
        }

        $event->addSuccess(function () use ($insertedRoutes, $updateCommands): void {
            $this->dispatchIndexingMessages($insertedRoutes, $updateCommands);
        });
    }

    /**
     * @param list<array{routeName: string, entityName: string}> $insertedRoutes
     * @param list<UpdateCommand> $updateCommands
     */
    private function dispatchIndexingMessages(array $insertedRoutes, array $updateCommands): void
    {
        $changedIds = [];
        foreach ($updateCommands as $command) {
            $changeSet = $command->getChangeSet();
            if ($changeSet !== null && !$changeSet->hasChanged('template')) {
                continue;
            }

            $primaryKey = $command->getDecodedPrimaryKey();
            $id = reset($primaryKey);
            if (\is_string($id)) {
                $changedIds[] = $id;
            }
        }

        $routes = $insertedRoutes;
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
