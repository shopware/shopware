<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\ContentSystem;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Routing\StorefrontContentRouteLoader;
use Symfony\Component\Routing\RouterInterface;

/**
 * Decorates SeoUrlGenerator to rewrite pathInfo for entities
 * that have content layout assignments, pointing them to the
 * content system's prefixed routes instead of legacy technical routes.
 *
 * @internal
 *
 * @final
 */
#[Package('inventory')]
class ContentAwareSeoUrlGenerator extends SeoUrlGenerator
{
    private readonly SeoUrlGenerator $inner;

    private readonly Connection $connection;

    private readonly RouterInterface $contentSystemRouter;

    /**
     * @internal
     */
    public function __construct(
        SeoUrlGenerator $inner,
        Connection $connection,
        RouterInterface $router,
        private readonly ContentSeoRouteRegistry $seoRouteRegistry,
    ) {
        $this->inner = $inner;
        $this->connection = $connection;
        $this->contentSystemRouter = $router;
    }

    /**
     * @param list<string|array<string, string>> $ids
     *
     * @return iterable<SeoUrlEntity>
     */
    public function generate(array $ids, string $template, SeoUrlRouteInterface $route, Context $context, SalesChannelEntity $salesChannel): iterable
    {
        $entityName = $route->getConfig()->getDefinition()->getEntityName();
        $descriptor = $this->seoRouteRegistry->findByEntityType($entityName);

        if ($descriptor === null) {
            yield from $this->inner->generate($ids, $template, $route, $context, $salesChannel);

            return;
        }

        $table = $descriptor->definition->getEntityName();
        $idColumn = $descriptor->definition->getContentLayoutEntityIdColumn();
        $contentSystemRoute = StorefrontContentRouteLoader::buildRouteName($descriptor->definition->getContentLayoutEntityType());

        $entityIds = array_values(array_filter($ids, 'is_string'));
        $assignedIds = $this->fetchAssignedEntityIds(
            $table,
            $idColumn,
            $entityIds,
            $salesChannel->getId(),
        );

        foreach ($this->inner->generate($ids, $template, $route, $context, $salesChannel) as $seoUrl) {
            if (isset($assignedIds[$seoUrl->getForeignKey()])) {
                $pathInfo = $this->contentSystemRouter->generate(
                    $contentSystemRoute,
                    [StorefrontContentRouteLoader::PARAMETER_ENTITY_ID => $seoUrl->getForeignKey()],
                );

                $seoUrl->setPathInfo($pathInfo);
            }

            yield $seoUrl;
        }
    }

    /**
     * @param list<string> $entityIds
     *
     * @return array<string, true>
     */
    private function fetchAssignedEntityIds(string $table, string $idColumn, array $entityIds, string $salesChannelId): array
    {
        if ($entityIds === []) {
            return [];
        }

        $queryBuilder = $this->connection->createQueryBuilder();

        /** @var list<string> $rows */
        $rows = $queryBuilder
            ->select('DISTINCT LOWER(HEX(' . $idColumn . '))')
            ->from($table)
            ->where($idColumn . ' IN (:entityIds)')
            ->andWhere('sales_channel_id = :salesChannelId OR sales_channel_id IS NULL')
            ->setParameter('entityIds', Uuid::fromHexToBytesList($entityIds), ArrayParameterType::STRING)
            ->setParameter('salesChannelId', Uuid::fromHexToBytes($salesChannelId))
            ->executeQuery()
            ->fetchFirstColumn();

        $result = [];
        foreach ($rows as $id) {
            $result[$id] = true;
        }

        return $result;
    }
}
