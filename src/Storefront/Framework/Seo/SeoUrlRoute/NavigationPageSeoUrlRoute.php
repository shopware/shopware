<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoTemplateReplacementVariable;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlExtractIdResult;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class NavigationPageSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.navigation.page';
    public const DEFAULT_TEMPLATE = '{% for part in category.seoBreadcrumb %}{{ part }}/{% endfor %}';

    /**
     * @var CategoryDefinition
     */
    private $categoryDefinition;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(CategoryDefinition $categoryDefinition, Connection $connection)
    {
        $this->categoryDefinition = $categoryDefinition;
        $this->connection = $connection;
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->categoryDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true,
            true
        );
    }

    public function prepareCriteria(Criteria $criteria): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
    }

    public function getMapping(Entity $category, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$category instanceof CategoryEntity) {
            throw new \InvalidArgumentException('Expected CategoryEntity');
        }

        $rootId = $this->detectRootId($category, $salesChannel);

        $breadcrumbs = $category->buildSeoBreadcrumb($rootId);
        $categoryJson = $category->jsonSerialize();
        $categoryJson['seoBreadcrumb'] = $breadcrumbs;

        $error = null;
        if (!$rootId) {
            $error = 'Category is not available for sales channel';
        }

        return new SeoUrlMapping(
            $category,
            ['navigationId' => $category->getId()],
            [
                'category' => $categoryJson,
            ],
            $error
        );
    }

    public function getSeoVariables(): array
    {
        return ['seoBreadcrumb' => new SeoTemplateReplacementVariable(CategoryTranslationDefinition::ENTITY_NAME, 'breadcrumb')];
    }

    public function extractIdsToUpdate(EntityWrittenContainerEvent $event): SeoUrlExtractIdResult
    {
        $ids = [];

        // check if a category was written. If this is the case, its Seo Url must be updated.
        $categoryEvent = $event->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        if ($categoryEvent) {
            $ids = $categoryEvent->getIds();
        }

        $children = $this->fetchChildren($ids);
        $ids = array_unique(array_merge($ids, $children));

        $mustReindex = $this->mustReindex($event);

        return new SeoUrlExtractIdResult($ids, $mustReindex);
    }

    private function detectRootId(CategoryEntity $category, ?SalesChannelEntity $salesChannel): ?string
    {
        if (!$salesChannel) {
            return null;
        }
        $path = array_filter(explode('|', (string) $category->getPath()));

        $navigationId = $salesChannel->getNavigationCategoryId();
        if ($navigationId === $category->getId() || in_array($navigationId, $path, true)) {
            return $navigationId;
        }

        $footerId = $salesChannel->getFooterCategoryId();
        if ($footerId === $category->getId() || in_array($footerId, $path, true)) {
            return $footerId;
        }

        $serviceId = $salesChannel->getServiceCategoryId();
        if ($serviceId === $category->getId() || in_array($serviceId, $path, true)) {
            return $serviceId;
        }

        return null;
    }

    private function mustReindex(EntityWrittenContainerEvent $event): bool
    {
        $domainEvent = $event->getEventByEntityName(SalesChannelDomainDefinition::ENTITY_NAME);
        if ($domainEvent) {
            foreach ($domainEvent->getExistences() as $existence) {
                if (!$existence->exists()) {
                    return true;
                }
            }
        }

        // check if a sales channel navigationCategory was updated...
        $salesChannelEvent = $event->getEventByEntityName(SalesChannelDefinition::ENTITY_NAME);
        if (!$salesChannelEvent) {
            return false;
        }

        $salesChannelPayloads = $salesChannelEvent->getPayloads();

        foreach ($salesChannelPayloads as $salesChannelPayload) {
            if (isset($salesChannelPayload['navigationCategoryId'])) {
                return true;
            }
            if (isset($salesChannelPayload['footerCategoryId'])) {
                return true;
            }
            if (isset($salesChannelPayload['serviceCategoryId'])) {
                return true;
            }
        }

        return false;
    }

    private function fetchChildren(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $query = $this->connection->createQueryBuilder();

        $query->select('category.id');
        $query->from('category');

        foreach ($ids as $id) {
            $key = 'id' . $id;
            $query->orWhere('category.path LIKE :' . $key);
            $query->setParameter($key, '%' . $id . '%');
        }

        $children = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        if (!$children) {
            return [];
        }

        return Uuid::fromBytesToHexList($children);
    }
}
