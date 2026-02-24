<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\ContentSystem;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Triggers SEO URL regeneration when content layout assignments change.
 *
 * When entities get assigned or unassigned from content layouts, their SEO URL
 * pathInfo must be regenerated to point to either the content system route or
 * the legacy technical route.
 *
 * @internal
 *
 * @final
 */
#[AsEventListener(event: EntityWrittenContainerEvent::class)]
#[Package('inventory')]
class ContentLayoutAssignmentSeoUrlSubscriber
{
    public function __construct(
        private readonly SeoUrlUpdater $seoUrlUpdater,
        private readonly Connection $connection,
        private readonly ContentSeoRouteRegistry $seoRouteRegistry,
    ) {
    }

    public function __invoke(EntityWrittenContainerEvent $event): void
    {
        foreach ($this->seoRouteRegistry as $descriptor) {
            $entityName = $descriptor->definition->getEntityName();
            $assignmentIds = $event->getPrimaryKeys($entityName);

            if ($assignmentIds === []) {
                continue;
            }

            $idColumn = $descriptor->definition->getContentLayoutEntityIdColumn();
            $entityIds = $this->fetchEntityIds($assignmentIds, $entityName, $idColumn);

            if ($entityIds === []) {
                continue;
            }

            $this->seoUrlUpdater->update($descriptor->legacySeoRouteName, $entityIds);
        }
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function fetchEntityIds(array $assignmentIds, string $table, string $idColumn): array
    {
        $queryBuilder = $this->connection->createQueryBuilder();

        /** @var list<string> $result */
        $result = $queryBuilder
            ->select('DISTINCT LOWER(HEX(' . $idColumn . '))')
            ->from($table)
            ->where('id IN (:ids)')
            ->setParameter('ids', Uuid::fromHexToBytesList($assignmentIds), ArrayParameterType::BINARY)
            ->executeQuery()
            ->fetchFirstColumn();

        return $result;
    }
}
