<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class AddWishlistProductRoute extends AbstractAddWishlistProductRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $wishlistRepository;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $wishlistRepository,
        SalesChannelRepositoryInterface $productRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->wishlistRepository = $wishlistRepository;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractAddWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.4.0")
     * @OA\Post(
     *      path="/customer/wishlist/add/{productId}",
     *      summary="Add a product to a wishlist",
     *      description="Adds a product to a customers wishlist.

**Important constraints**

* Anonymous (not logged-in) customers can not have wishlists.
* The wishlist feature has to be activated.",
     *      operationId="addProductOnWishlist",
     *      tags={"Store API", "Wishlist"},
     *      @OA\Parameter(
     *        name="productId",
     *        in="path",
     *        description="Identifier of the product to be added.",
     *        @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns a success response.",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @LoginRequired()
     * @Route("/store-api/customer/wishlist/add/{productId}", name="store-api.customer.wishlist.add", methods={"POST"})
     */
    public function add(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannel()->getId())) {
            throw new CustomerWishlistNotActivatedException();
        }

        $this->validateProduct($productId, $context);
        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $this->wishlistRepository->upsert([
            [
                'id' => $wishlistId,
                'customerId' => $customer->getId(),
                'salesChannelId' => $context->getSalesChannel()->getId(),
                'products' => [
                    [
                        'productId' => $productId,
                        'productVersionId' => Defaults::LIVE_VERSION,
                    ],
                ],
            ],
        ], $context->getContext());

        return new SuccessResponse();
    }

    private function getWishlistId(SalesChannelContext $context, string $customerId): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('customerId', $customerId),
            new EqualsFilter('salesChannelId', $context->getSalesChannel()->getId()),
        ]));

        $wishlistIds = $this->wishlistRepository->searchIds($criteria, $context->getContext());

        if ($wishlistIds->firstId() === null) {
            return Uuid::randomHex();
        }

        return $wishlistIds->firstId();
    }

    private function validateProduct(string $productId, SalesChannelContext $context): void
    {
        $productsIds = $this->productRepository->searchIds(new Criteria([$productId]), $context);

        if ($productsIds->firstId() === null) {
            throw new ProductNotFoundException($productId);
        }
    }
}
