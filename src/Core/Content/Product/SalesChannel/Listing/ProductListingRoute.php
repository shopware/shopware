<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Listing;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductListingRoute extends AbstractProductListingRoute
{
    /**
     * @var ProductListingLoader
     */
    private $listingLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ProductDefinition
     */
    private $definition;

    /**
     * @var RequestCriteriaBuilder
     */
    private $criteriaBuilder;

    public function __construct(
        ProductListingLoader $listingLoader,
        EventDispatcherInterface $eventDispatcher,
        ProductDefinition $definition,
        RequestCriteriaBuilder $criteriaBuilder
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->listingLoader = $listingLoader;
        $this->definition = $definition;
        $this->criteriaBuilder = $criteriaBuilder;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Entity("product")
     * @OA\Post(
     *      path="/product-listing/{categoryId}",
     *      description="Loads products from listing",
     *      operationId="readProductListing",
     *      tags={"Store API","Language"},
     *      @OA\Response(
     *          response="200",
     *          description="Found products",
     *          @OA\JsonContent(ref="#/definitions/ProductListingResult")
     *     )
     * )
     * @Route("/store-api/v{version}/product-listing/{categoryId}", name="store-api.product.listing", methods={"POST"})
     */
    public function load(string $categoryId, Request $request, SalesChannelContext $salesChannelContext, ?Criteria $criteria = null): ProductListingRouteResponse
    {
        // @deprecated tag:v6.4.0 - Criteria will be required
        if (!$criteria) {
            $criteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $this->definition, $salesChannelContext->getContext());
        }
        $criteria->addFilter(
            new ProductAvailableFilter($salesChannelContext->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $categoryId)
        );
        $criteria->addAssociation('options.group');

        $this->eventDispatcher->dispatch(
            new ProductListingCriteriaEvent($request, $criteria, $salesChannelContext)
        );

        $result = $this->listingLoader->load($criteria, $salesChannelContext);

        $result = ProductListingResult::createFrom($result);

        $result->addCurrentFilter('navigationId', $categoryId);

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $result, $salesChannelContext)
        );

        return new ProductListingRouteResponse($result);
    }
}
