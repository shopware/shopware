<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContentSystem\Cache;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ContentSystem\ContentSection;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('discovery')]
class CacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly Connection $connection,
        private readonly EntityCacheTagResolver $cacheTagResolver,
        private readonly DefinitionInstanceRegistry $definitionRegistry,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ];
    }

    public function onEntityWritten(EntityWrittenContainerEvent $event): void
    {
        $this->invalidateContentLayout($event);
        $this->invalidateProductContentLayout($event);
        $this->invalidateCategoryContentLayout($event);
        $this->invalidateLandingPageContentLayout($event);
        $this->invalidateHeaderContentLayout($event);
        $this->invalidateFooterContentLayout($event);
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

    private function invalidateProductContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys('product_content_layout');

        if ($ids === []) {
            return;
        }

        $productIds = $this->getProductIdsFromAssignments($ids);

        if ($productIds === []) {
            return;
        }

        $definition = $this->definitionRegistry->get(ProductDefinition::class);
        $tags = array_filter(array_map(
            fn (string $id) => $this->cacheTagResolver->resolve($definition, $id),
            $productIds
        ));

        $this->cacheInvalidator->invalidate($tags);
    }

    private function invalidateCategoryContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys('category_content_layout');

        if ($ids === []) {
            return;
        }

        $categoryIds = $this->getCategoryIdsFromAssignments($ids);

        if ($categoryIds === []) {
            return;
        }

        $definition = $this->definitionRegistry->get(CategoryDefinition::class);
        $tags = array_filter(array_map(
            fn (string $id) => $this->cacheTagResolver->resolve($definition, $id),
            $categoryIds
        ));

        $this->cacheInvalidator->invalidate($tags);
    }

    private function invalidateLandingPageContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys('landing_page_content_layout');

        if ($ids === []) {
            return;
        }

        $landingPageIds = $this->getLandingPageIdsFromAssignments($ids);

        if ($landingPageIds === []) {
            return;
        }

        $definition = $this->definitionRegistry->get(LandingPageDefinition::class);
        $tags = array_filter(array_map(
            fn (string $id) => $this->cacheTagResolver->resolve($definition, $id),
            $landingPageIds
        ));

        $this->cacheInvalidator->invalidate($tags);
    }

    private function invalidateHeaderContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys('header_content_layout');

        if ($ids === []) {
            return;
        }

        $layoutIds = $this->getLayoutIdsFromHeaderAssignments($ids);

        if ($layoutIds === []) {
            return;
        }

        $tags = [];
        foreach ($layoutIds as $layoutId) {
            $tags = array_merge($tags, ContentSection::HEADER->buildRouteCacheTags($layoutId));
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    private function invalidateFooterContentLayout(EntityWrittenContainerEvent $event): void
    {
        $ids = $event->getPrimaryKeys('footer_content_layout');

        if ($ids === []) {
            return;
        }

        $layoutIds = $this->getLayoutIdsFromFooterAssignments($ids);

        if ($layoutIds === []) {
            return;
        }

        $tags = [];
        foreach ($layoutIds as $layoutId) {
            $tags = array_merge($tags, ContentSection::FOOTER->buildRouteCacheTags($layoutId));
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function getProductIdsFromAssignments(array $assignmentIds): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(product_id)) FROM product_content_layout WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($assignmentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function getCategoryIdsFromAssignments(array $assignmentIds): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(category_id)) FROM category_content_layout WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($assignmentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function getLandingPageIdsFromAssignments(array $assignmentIds): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(landing_page_id)) FROM landing_page_content_layout WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($assignmentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function getLayoutIdsFromHeaderAssignments(array $assignmentIds): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(content_layout_id)) FROM header_content_layout WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($assignmentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $assignmentIds
     *
     * @return list<string>
     */
    private function getLayoutIdsFromFooterAssignments(array $assignmentIds): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(content_layout_id)) FROM footer_content_layout WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($assignmentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
