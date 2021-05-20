<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotActivatedException;
use Shopware\Core\Checkout\Customer\Exception\CustomerWishlistNotFoundException;
use Shopware\Core\Checkout\Customer\Exception\WishlistProductNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SuccessResponse;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class RemoveWishlistProductRoute extends AbstractRemoveWishlistProductRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $wishlistRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(
        EntityRepositoryInterface $wishlistRepository,
        EntityRepositoryInterface $productRepository,
        SystemConfigService $systemConfigService
    ) {
        $this->wishlistRepository = $wishlistRepository;
        $this->productRepository = $productRepository;
        $this->systemConfigService = $systemConfigService;
    }

    public function getDecorated(): AbstractRemoveWishlistProductRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.4.0")
     * @OA\Delete(
     *      path="/customer/wishlist/delete/{productId}",
     *      summary="Remove a product from a wishlist",
     *      description="Removes a product from a customer's wishlist.

**Important constraints**

* Anonymous (not logged-in) customers can not have wishlists.
* The wishlist feature has to be activated.",
     *      operationId="deleteProductOnWishlist",
     *      tags={"Store API", "Wishlist"},
     *      @OA\Parameter(
     *        name="productId",
     *        in="path",
     *        description="The identifier of the product to be removed from the wishlist.",
     *        @OA\Schema(type="string", pattern="^[0-9a-f]{32}$"),
     *        required=true
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Returns a success response indicating a successful removal.",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     ),
     *     @OA\Response(
     *          response="404",
     *          description="The removal of the product failed. Probably because the product could not be found on the wishlist.",
     *          @OA\JsonContent(ref="#/components/schemas/failure")
     *     ),
     * )
     * @LoginRequired()
     * @Route("/store-api/customer/wishlist/delete/{productId}", name="store-api.customer.wishlist.delete", methods={"DELETE"})
     */
    public function delete(string $productId, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getSalesChannel()->getId())) {
            throw new CustomerWishlistNotActivatedException();
        }

        $wishlistId = $this->getWishlistId($context, $customer->getId());

        $wishlistProductId = $this->getWishlistProductId($wishlistId, $productId, $context);

        $this->productRepository->delete([
            [
                'id' => $wishlistProductId,
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
            throw new CustomerWishlistNotFoundException();
        }

        return $wishlistIds->firstId();
    }

    private function getWishlistProductId(string $wishlistId, string $productId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('wishlistId', $wishlistId),
            new EqualsFilter('productId', $productId),
            new EqualsFilter('productVersionId', Defaults::LIVE_VERSION),
        ]));
        $wishlistProductIds = $this->productRepository->searchIds($criteria, $context->getContext());

        if ($wishlistProductIds->firstId() === null) {
            throw new WishlistProductNotFoundException($productId);
        }

        return $wishlistProductIds->firstId();
    }
}
