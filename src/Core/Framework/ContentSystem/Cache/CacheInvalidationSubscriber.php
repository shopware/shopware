<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
#[AsEventListener(event: EntityWrittenContainerEvent::class)]
class CacheInvalidationSubscriber
{
    /**
     * @var array<string, ContentSection>
     */
    private readonly array $sectionAssignments;

    /**
     * Header and footer live in the Storefront bundle, so their table names arrive as a container parameter
     * instead of an import; a Core-only installation passes an empty map.
     *
     * @param array<string, string> $sectionAssignmentEntities assignment table name => ContentSection value
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
        private readonly EntityCacheTagResolver $cacheTagResolver,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
        array $sectionAssignmentEntities,
    ) {
        $this->sectionAssignments = array_map(ContentSection::from(...), $sectionAssignmentEntities);
    }

    public function __invoke(EntityWrittenContainerEvent $event): void
    {
        $this->invalidateContentLayout($event);
        $this->invalidateEntityContentLayout($event, ProductContentLayoutDefinition::ENTITY_NAME, 'product_id', ProductDefinition::class);
        $this->invalidateEntityContentLayout($event, CategoryContentLayoutDefinition::ENTITY_NAME, 'category_id', CategoryDefinition::class);
        $this->invalidateEntityContentLayout($event, LandingPageContentLayoutDefinition::ENTITY_NAME, 'landing_page_id', LandingPageDefinition::class);

        foreach ($this->sectionAssignments as $entityName => $section) {
            $this->invalidateSectionContentLayout($event, $entityName, $section);
        }
    }

    /**
     * A deleted assignment row is gone by the time the written event fires, so the entity tags are resolved before
     * the delete and invalidated once it succeeded.
     */
    public function beforeDelete(EntityDeleteEvent $event): void
    {
        $assignments = [
            ProductContentLayoutDefinition::ENTITY_NAME => ['product_id', ProductDefinition::class],
            CategoryContentLayoutDefinition::ENTITY_NAME => ['category_id', CategoryDefinition::class],
            LandingPageContentLayoutDefinition::ENTITY_NAME => ['landing_page_id', LandingPageDefinition::class],
        ];

        $tags = [];

        foreach ($assignments as $entityName => [$column, $definitionClass]) {
            $ids = array_values(array_filter($event->getIds($entityName), '\is_string'));

            if ($ids === []) {
                continue;
            }

            $definition = $this->definitionRegistry->get($definitionClass);

            foreach ($this->fetchIdsFromAssignments($ids, $entityName, $column) as $entityId) {
                $tags[] = $this->cacheTagResolver->resolve($definition, $entityId);
            }
        }

        $tags = array_values(array_filter($tags));

        if ($tags === []) {
            return;
        }

        $event->addSuccess(fn () => $this->cacheInvalidator->invalidate($tags));
    }

    /**
     * Every page of the type carries the default layout key tag, because a default change applies to all sales channels
     * inheriting it; the event fires regardless of the silent flag.
     */
    public function invalidateDefaultLayout(SystemConfigChangedEvent $event): void
    {
        $defaultLayoutKeys = [
            ProductContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT,
            CategoryContentLayoutDefinition::CONFIG_KEY_DEFAULT_CONTENT_LAYOUT,
        ];

        if (!\in_array($event->getKey(), $defaultLayoutKeys, true)) {
            return;
        }

        $this->cacheInvalidator->invalidate([SystemConfigService::buildName($event->getKey())]);
    }

    private function invalidateContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys(ContentLayoutDefinition::ENTITY_NAME);

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
