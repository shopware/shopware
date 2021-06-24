<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductListRoute extends AbstractProductListRoute
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    public function __construct(SalesChannelRepositoryInterface $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    public function getDecorated(): AbstractProductListRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.2.0")
     * @Entity("product")
     * @OA\Post(
     *      path="/product",
     *      summary="Fetch a list of products",
     *      description="List products that match the given criteria. For performance ressons a limit should always be set.",
     *      operationId="readProduct",
     *      tags={"Store API", "Product"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Entity search result containing products",
     *          @OA\JsonContent(
     *              type="object",
     *              allOf={
     *                  @OA\Schema(ref="#/components/schemas/EntitySearchResult"),
     *                  @OA\Schema(type="object",
     *                      @OA\Property(
     *                          type="array",
     *                          property="elements",
     *                          @OA\Items(ref="#/components/schemas/Product")
     *                      )
     *                  )
     *              }
     *          )
     *     )
     * )
     * @Route("/store-api/product", name="store-api.product.search", methods={"GET", "POST"})
     */
    public function load(Criteria $criteria, SalesChannelContext $context): ProductListResponse
    {
        return new ProductListResponse($this->productRepository->search($criteria, $context));
    }
}
