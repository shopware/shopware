<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 *
 * @final
 */
#[AsEventListener(event: EntityWrittenContainerEvent::class)]
#[Package('framework')]
class CacheInvalidationSubscriber
{
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
        private readonly EntityCacheTagResolver $cacheTagResolver,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public function __invoke(EntityWrittenContainerEvent $event): void
    {
        $this->invalidateContentLayout($event);
        $this->invalidateEntityContentLayout($event, 'product_content_layout', 'product_id', ProductDefinition::class);
        $this->invalidateEntityContentLayout($event, 'category_content_layout', 'category_id', CategoryDefinition::class);
        $this->invalidateEntityContentLayout($event, 'landing_page_content_layout', 'landing_page_id', LandingPageDefinition::class);
        $this->invalidateSectionContentLayout($event, 'header_content_layout', ContentSection::HEADER);
        $this->invalidateSectionContentLayout($event, 'footer_content_layout', ContentSection::FOOTER);
    }

    private function invalidateContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys('content_layout');

        if ($ids === []) {
            return;
        }

        $tags = array_map(
            static fn (string $id) => ContentSection::MAIN->buildLayoutTag($id),
            $ids
        );

        $this->cacheInvalidator->invalidate($tags);
    }

    /**
     * @param class-string $definitionClass
     */
    private function invalidateEntityContentLayout(
        EntityWrittenContainerEvent $event,
        string $entityName,
        string $column,
        string $definitionClass,
    ): void {
        $ids = $event->getPrimaryKeys($entityName);

        if ($ids === []) {
            return;
        }

        $entityIds = $this->fetchIdsFromAssignments($ids, $entityName, $column);

        if ($entityIds === []) {
            return;
        }

        $definition = $this->definitionRegistry->get($definitionClass);
        $tags = array_filter(array_map(
            fn (string $id) => $this->cacheTagResolver->resolve($definition, $id),
            $entityIds
        ));

        $this->cacheInvalidator->invalidate($tags);
    }

    private function invalidateSectionContentLayout(
        EntityWrittenContainerEvent $event,
        string $entityName,
        ContentSection $section,
    ): void {
        $ids = $event->getPrimaryKeys($entityName);

        if ($ids === []) {
            return;
        }

        $layoutIds = $this->fetchIdsFromAssignments($ids, $entityName, 'content_layout_id');

        if ($layoutIds === []) {
            return;
        }

        $tags = array_merge([], ...array_map(
            static fn (string $layoutId) => $section->buildRouteCacheTags($layoutId),
            $layoutIds
        ));

        $this->cacheInvalidator->invalidate($tags);
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function fetchIdsFromAssignments(array $assignmentIds, string $table, string $column): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(' . $column . ')) FROM ' . $table . ' WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($assignmentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
