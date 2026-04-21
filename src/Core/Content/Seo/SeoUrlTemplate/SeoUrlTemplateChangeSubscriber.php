<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo\SeoUrlTemplate;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Automatically regenerates existing SEO URLs when an SEO URL template is changed
 * in Settings > Shop > SEO, so merchants (especially on SaaS) don't need to wait
 * for / trigger the SEO indexer manually.
 *
 * The actual regeneration is dispatched to the message bus so iterating every
 * product/category of the affected route does not block the admin save.
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
            'seo_url_template.written' => 'onSeoUrlTemplateWritten',
        ];
    }

    public function onSeoUrlTemplateWritten(EntityWrittenEvent $event): void
    {
        $changedIds = [];
        foreach ($event->getWriteResults() as $writeResult) {
            // Only re-index when the template itself changed; other field changes
            // (for example custom fields) must not trigger expensive reindexing.
            if (!$writeResult->hasPayload('template')) {
                continue;
            }

            $primaryKey = $writeResult->getPrimaryKey();
            if (!\is_string($primaryKey)) {
                continue;
            }

            $changedIds[] = $primaryKey;
        }

        if ($changedIds === []) {
            return;
        }

        foreach ($this->loadRoutesForTemplates($changedIds) as $route) {
            if ($route['routeName'] === '' || $route['entityName'] === '') {
                continue;
            }

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
