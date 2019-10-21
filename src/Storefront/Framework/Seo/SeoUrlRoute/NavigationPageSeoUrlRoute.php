<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Seo\SeoUrlRoute;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Seo\SeoUrlRoute\SeoUrlExtractIdResult;
use Shopware\Core\Framework\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Framework\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Framework\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class NavigationPageSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'frontend.navigation.page';
    public const DEFAULT_TEMPLATE = '{% for part in breadcrumb %}{{ part }}/{% endfor %}';

    /**
     * @var CategoryDefinition
     */
    private $categoryDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    public function __construct(CategoryDefinition $categoryDefinition, EntityRepositoryInterface $categoryRepository)
    {
        $this->categoryDefinition = $categoryDefinition;
        $this->categoryRepository = $categoryRepository;
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->categoryDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE
        );
    }

    public function prepareCriteria(Criteria $criteria): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAssociation('navigationSalesChannels');
    }

    public function getMapping(Entity $category, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        if (!$category instanceof CategoryEntity) {
            throw new \InvalidArgumentException('Expected CategoryEntity');
        }

        $breadcrumbs = $category->buildSeoBreadcrumb($salesChannel ? $salesChannel->getNavigationCategoryId() : null);

        return new SeoUrlMapping(
            $category,
            ['navigationId' => $category->getId()],
            [
                'category' => $category->jsonSerialize(),
                'breadcrumb' => $breadcrumbs,
            ]
        );
    }

    public function extractIdsToUpdate(EntityWrittenContainerEvent $event): SeoUrlExtractIdResult
    {
        $ids = [];

        // check if a category was written. If this is the case, its Seo Url must be updated.
        $categoryEvent = $event->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        if ($categoryEvent) {
            $ids = $categoryEvent->getIds();
        }

        /** @var bool $mustReindex */
        $mustReindex = false;
        // check if a sales channel navigationCategory was updated...
        $salesChannelEvent = $event->getEventByEntityName(SalesChannelDefinition::ENTITY_NAME);
        if ($salesChannelEvent) {
            $salesChannelPayloads = $salesChannelEvent->getPayloads();
            $affectedIds = [[]];

            foreach ($salesChannelPayloads as $salesChannelPayload) {
                // ... if this is the case, the navigation category and _all_ of it's children must be updated.
                if (isset($salesChannelPayload['navigationCategoryId'])) {
                    $navigationCategoryId = $salesChannelPayload['navigationCategoryId'];
                    $affectedIds[] = [$navigationCategoryId];

                    $context = Context::createDefaultContext();
                    $affectedIds[] = $context->disableCache(function (Context $context) use ($navigationCategoryId) {
                        return $this->categoryRepository->searchIds(
                            (new Criteria())->addFilter(new ContainsFilter('path', $navigationCategoryId)),
                            $context
                        )->getIds();
                    });
                }
            }

            $mustReindex = count(array_merge([], ...$affectedIds)) > 0;
        }

        return new SeoUrlExtractIdResult($ids, $mustReindex);
    }
}
