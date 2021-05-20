<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\CartDeletedEvent;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @RouteScope(scopes={"store-api"})
 */
class CartDeleteRoute extends AbstractCartDeleteRoute
{
    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(CartPersisterInterface $cartPersister, EventDispatcherInterface $eventDispatcher)
    {
        $this->cartPersister = $cartPersister;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getDecorated(): AbstractCartDeleteRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @OA\Delete(
     *      path="/checkout/cart",
     *      summary="Delete a cart",
     *      description="This route deletes the cart of the customer.",
     *      operationId="deleteCart",
     *      tags={"Store API", "Cart"},
     *      @OA\Response(
     *          response="204",
     *          description="Successfully deleted the cart",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @Route("/store-api/checkout/cart", name="store-api.checkout.cart.delete", methods={"DELETE"})
     */
    public function delete(SalesChannelContext $context): NoContentResponse
    {
        $this->cartPersister->delete($context->getToken(), $context);

        $cartDeleteEvent = new CartDeletedEvent($context);
        $this->eventDispatcher->dispatch($cartDeleteEvent);

        return new NoContentResponse();
    }
}
