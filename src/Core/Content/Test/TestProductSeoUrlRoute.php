<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlMapping;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class TestProductSeoUrlRoute implements SeoUrlRouteInterface
{
    public const ROUTE_NAME = 'test.product.page';
    public const DEFAULT_TEMPLATE = '{{ product.id }}';

    private ProductDefinition $productDefinition;

    public function __construct(ProductDefinition $productDefinition)
    {
        $this->productDefinition = $productDefinition;
    }

    /**
     * @Route("/test/{productId}", name="test.product.page", options={"seo"=true}, methods={"GET"})
     */
    public function route(): Response
    {
        return new Response();
    }

    public function getConfig(): SeoUrlRouteConfig
    {
        return new SeoUrlRouteConfig(
            $this->productDefinition,
            self::ROUTE_NAME,
            self::DEFAULT_TEMPLATE,
            true
        );
    }

    public function prepareCriteria(Criteria $criteria/*, SalesChannelEntity $salesChannel */): void
    {
        // no-op, dummy implementation
    }

    /**
     * @param ProductEntity $entity
     */
    public function getMapping(Entity $entity, ?SalesChannelEntity $salesChannel): SeoUrlMapping
    {
        return new SeoUrlMapping(
            $entity,
            ['productId' => $entity->getId()],
            ['product' => $entity->jsonSerialize()]
        );
    }
}
