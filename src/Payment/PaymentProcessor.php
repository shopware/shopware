<?php declare(strict_types=1);

namespace Shopware\Payment;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Api\Order\Collection\OrderTransactionBasicCollection;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Api\Order\Struct\OrderDetailStruct;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Framework\Routing\Router;
use Shopware\Payment\Exception\InvalidOrderException;
use Shopware\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Payment\PaymentHandler\PaymentHandlerInterface;
use Shopware\Payment\Struct\PaymentTransaction;
use Shopware\Payment\Token\PaymentTransactionTokenFactory;
use Shopware\Payment\Token\PaymentTransactionTokenFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentProcessor
{
    /**
     * @var PaymentTransactionTokenFactory
     */
    private $tokenFactory;

    /**
     * @var OrderRepository
     */
    private $orderRepository;

    /**
     * @var PaymentMethodRepository
     */
    private $paymentMethodRepository;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(
        PaymentTransactionTokenFactoryInterface $tokenFactory,
        OrderRepository $orderRepository,
        PaymentMethodRepository $paymentMethodRepository,
        Router $router,
        ContainerInterface $container
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->orderRepository = $orderRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->router = $router;
        $this->container = $container;
    }

    /**
     * @param string      $orderId
     * @param ShopContext $shopContext
     *
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     *
     * @return null|RedirectResponse
     */
    public function process(string $orderId, ShopContext $shopContext): ?RedirectResponse
    {
        /** @var OrderDetailStruct $order */
        $order = $this->orderRepository->readDetail([$orderId], $shopContext)->first();

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        /** @var OrderTransactionBasicCollection $transactions */
        $transactions = $order->getTransactions()->filterByOrderStateId(Defaults::ORDER_TRANSACTION_OPEN);

        foreach ($transactions as $transaction) {
            $token = $this->tokenFactory->generateToken($transaction);

            $returnUrl = $this->assembleReturnUrl($token);

            $paymentTransaction = new PaymentTransaction(
                $transaction->getId(),
                $transaction->getPaymentMethodId(),
                $order,
                $transaction->getAmount(),
                $returnUrl
            );

            $handler = $this->getPaymentHandlerById($transaction->getPaymentMethodId(), $shopContext);

            $response = $handler->pay($paymentTransaction, $shopContext);
            if ($response) {
                return $response;
            }
        }

        return null;
    }

    private function getPaymentHandlerById(string $paymentMethodId, ShopContext $context): PaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->readBasic([$paymentMethodId], $context);

        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        try {
            return $this->container->get($paymentMethod->getClass());
        } catch (NotFoundExceptionInterface $e) {
            throw new UnknownPaymentMethodException($paymentMethod->getClass());
        }
    }

    private function assembleReturnUrl(string $token): string
    {
        return $this->router->generate(
            'checkout_finalize_transaction',
            ['_sw_payment_token' => $token],
            Router::ABSOLUTE_URL
        );
    }
}
