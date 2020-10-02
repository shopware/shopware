<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Review;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class ProductReviewRoute extends AbstractProductReviewRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    public function __construct(EntityRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    public function getDecorated(): AbstractProductReviewRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Entity("product_review")
     * @OA\Post(
     *      path="/product/{productId}/reviews",
     *      description="",
     *      operationId="readProductReviews",
     *      tags={"Store API","Product"},
     *      @OA\Response(
     *          response="200",
     *          description="Found reviews",
     *          @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/product_review_flat"))
     *     )
     * )
     * @Route("/store-api/v{version}/product/{productId}/reviews", name="store-api.product-review.list", methods={"POST"})
     */
    public function load(string $productId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductReviewRouteResponse
    {
        $active = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
        // ToDo NEXT-10590 - Reimplement check to let users see their own, not published reviews, if display works again

        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                $active,
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('product.id', $productId),
                    new EqualsFilter('product.parentId', $productId),
                ]),
            ])
        );

        $result = $this->repository->search($criteria, $context->getContext());

        return new ProductReviewRouteResponse($result);
    }
}
