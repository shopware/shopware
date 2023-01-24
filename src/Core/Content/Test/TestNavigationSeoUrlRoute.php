<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['store-api']])]
class TestNavigationSeoUrlRoute implements SeoUrlRouteInterface
{
    final public const ROUTE_NAME = 'test.navigation.page';
    final public const DEFAULT_TEMPLATE = '{{ id }}';

    public function __construct(private readonly CategoryDefinition $categoryDefinition)
    {
    }

    #[Route(path: '/test/{navigationId}', name: 'test.navigation.page', options: ['seo' => true], methods: ['GET'])]
    public function route(): Response
    {
        return new Response();
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->categoryDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true
        );
    }

    public function prepareCriteria(Criteria $criteria, SalesChannelEntity $salesChannel): void
    {
        $criteria->addFilter(new EqualsFilter('active', true));
    }

    /**
     * @param CategoryEntity $entity
     */
    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        return new SeoUrlMapping(
            $entity,
            ['navigationId' => $entity->getId()],
            ['id' => $entity->getId()]
        );
    }
}
