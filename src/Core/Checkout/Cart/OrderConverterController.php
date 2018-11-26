<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderStruct;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class OrderConverterController extends AbstractController
{
    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var CartPersisterInterface
     */
    private $cartPersister;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    public function __construct(
        OrderConverter $orderConverter,
        CartPersisterInterface $cartPersister,
        RepositoryInterface $orderRepository)
    {
        $this->orderConverter = $orderConverter;
        $this->cartPersister = $cartPersister;
        $this->orderRepository = $orderRepository;
    }

    /**
     * @Route("/api/_action/v{version}/order/{orderId}/convert-to-cart/", name="api.action.order.convert-to-cart", methods={"POST"})
     *
     * @throws CartTokenNotFoundException
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws LineItemNotStackableException
     * @throws MixedLineItemTypeException
     * @throws InvalidOrderException
     */
    public function convertToCart(string $orderId, Context $context)
    {
        /** @var OrderStruct|null $order */
        $order = $this->orderRepository->read(new ReadCriteria([$orderId]), $context)->get($orderId);

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $convertedCart = $this->orderConverter->convertToCart($order, $context);

        $this->cartPersister->save(
            $convertedCart,
            $this->orderConverter->assembleCheckoutContext($order, $context)
        );

        return new JsonResponse(['token' => $convertedCart->getToken()]);
    }
}
