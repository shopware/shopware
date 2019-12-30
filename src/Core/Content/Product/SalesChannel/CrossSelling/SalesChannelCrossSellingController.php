<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\CrossSelling;

use OpenApi\Annotations as OA;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Api\Converter\ApiVersionConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"sales-channel-api"})
 */
class SalesChannelCrossSellingController extends AbstractController
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var ApiVersionConverter
     */
    private $apiVersionConverter;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    /**
     * @var ProductCrossSellingDefinition
     */
    private $crossSellingDefinition;

    /**
     * @var ProductStreamBuilderInterface
     */
    private $productStreamBuilder;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        ApiVersionConverter $apiVersionConverter,
        ProductDefinition $productDefinition,
        ProductCrossSellingDefinition $crossSellingDefinition,
        ProductStreamBuilderInterface $productStreamBuilder
    ) {
        $this->productRepository = $productRepository;
        $this->apiVersionConverter = $apiVersionConverter;
        $this->productDefinition = $productDefinition;
        $this->crossSellingDefinition = $crossSellingDefinition;
        $this->productStreamBuilder = $productStreamBuilder;
    }

    /**
     * * @OA\Get(
     *      path="/product/{id}/cross-selling",
     *      description="Get the cross selling products for given product",
     *      operationId="getCrossSelling",
     *      tags={"Sales Channel Api"},
     *      @OA\Parameter(
     *          parameter="id",
     *          name="id",
     *          in="path",
     *          description="Id of the product from which the cross-selling products should be loaded",
     *          @OA\Schema(type="string"),
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="The list of found cross selling products",
     *          @OA\JsonContent(
     *              type="array",
     *              @OA\Items(
     *                  type="object",
     *                  @OA\Property(
     *                      property="total",
     *                      type="integer",
     *                      description="The total amount of products in this cross selling",
     *                  ),
     *                  @OA\Property(
     *                      property="crossSelling",
     *                      ref="#/components/schemas/product_cross_selling_flat",
     *                  ),
     *                  @OA\Property(
     *                      property="products",
     *                      type="array",
     *                      description="The products for this cross-selling",
     *                      @OA\Items(ref="#/components/schemas/product_flat",),
     *                  ),
     *              ),
     *          ),
     *      ),
     *      @OA\Response(
     *          response="404",
     *          ref="#/components/responses/404"
     *      ),
     *     @OA\Response(
     *          response="401",
     *          ref="#/components/responses/401"
     *      )
     * )
     *CrossSellingLoader
     * @Route("/sales-channel-api/v{version}/product/{id}/cross-selling", name="sales-channel-api.product.cross-selling", methods={"GET"})
     */
    public function getCrossSelling(string $id, int $version, SalesChannelContext $context): JsonResponse
    {
        $productCriteria = $this->getProductCriteria($id);

        $product = $this->productRepository->search($productCriteria, $context)->get($id);

        if (!$product) {
            throw new NotFoundHttpException(sprintf('Product with id "%s" not found.', $id));
        }

        $data = [];
        foreach ($product->getCrossSellings() as $crossSelling) {
            $result = $this->loadProductsForCrossSelling($crossSelling, $context);
            /** @var ProductCollection $products */
            $products = $result->getEntities();

            $data[] = [
                'total' => $result->getTotal(),
                'crossSelling' => $this->apiVersionConverter->convertEntity(
                    $this->crossSellingDefinition,
                    $crossSelling,
                    $version
                ),
                'products' => $this->convertProducts($products, $version),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    private function getProductCriteria(string $id): Criteria
    {
        $productCriteria = new Criteria([$id]);
        $productCriteria->getAssociation('crossSellings')
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING));

        return $productCriteria;
    }

    private function loadProductsForCrossSelling(ProductCrossSellingEntity $crossSelling, SalesChannelContext $context): EntitySearchResult
    {
        $filters = $this->productStreamBuilder->buildFilters(
            $crossSelling->getProductStreamId(),
            $context->getContext()
        );

        $criteria = new Criteria();
        $criteria->addFilter(...$filters)
            ->setLimit($crossSelling->getLimit())
            ->addSorting($crossSelling->getSorting());

        return $this->productRepository->search($criteria, $context);
    }

    private function convertProducts(ProductCollection $products, int $version): array
    {
        $result = [];

        foreach ($products as $product) {
            $result[] = $this->apiVersionConverter->convertEntity(
                $this->productDefinition,
                $product,
                $version
            );
        }

        return $result;
    }
}
