<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Entity;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @internal (flag:FEATURE_NEXT_10549)
 *
 * @RouteScope(scopes={"store-api"})
 */
class LoadGuestWishlistRoute extends AbstractLoadGuestWishlistRoute
{
    private const MAXIMUM_GUEST_PRODUCTS = 100;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractLoadWishlistRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.5.0")
     * @Entity("product")
     * @OA\Post(
     *      path="/guest/wishlist",
     *      summary="Fetch guest wishlist",
     *      operationId="readGuestWishlist",
     *      tags={"Store API", "Wishlist"},
     *      @OA\Parameter(name="Api-Basic-Parameters"),
     *      @OA\Response(
     *          response="200",
     *          description="Success",
     *          @OA\JsonContent(ref="#/components/schemas/LoadGuestWishlistRouteResponse")
     *     )
     * )
     * @Route("/store-api/v{version}/guest/wishlist", name="store-api.guest.wishlist.load", methods={"GET", "POST"})
     */
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): LoadGuestWishlistRouteResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannel()->getId())) {
            throw new CustomerWishlistNotActivatedException();
        }

        $productIds = $request->get('productIds', []);

        if (!\is_array($productIds)) {
            throw new InvalidArgumentException('Argument $productIds is not an array');
        }

        $products = $this->loadProducts($productIds, $criteria, $context);

        return new LoadGuestWishlistRouteResponse($products);
    }

    private function loadProducts(array $productIds, Criteria $criteria, SalesChannelContext $context): EntitySearchResult
    {
        $limit = $criteria->getLimit();

        if (!$limit || $limit >= self::MAXIMUM_GUEST_PRODUCTS) {
            $criteria->setLimit(self::MAXIMUM_GUEST_PRODUCTS);
        }

        $productIds = array_filter($productIds, function ($productId) {
            return Uuid::isValid($productId);
        });

        if (empty($productIds)) {
            return new EntitySearchResult(
                0,
                new ProductCollection(),
                $this->productRepository->aggregate($criteria, $context),
                $criteria,
                $context->getContext()
            );
        }

        $criteria->setIds($productIds);

        return $this->productRepository->search($criteria, $context);
    }
}
