<?php declare(strict_types=1);

namespace Shopware\Payment;

use Shopware\Api\Order\Collection\OrderTransactionBasicCollection;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Api\Order\Struct\OrderDetailStruct;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Payment\Exception\InvalidOrderException;
use Shopware\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Payment\PaymentHandler\PaymentHandlerInterface;
use Shopware\Payment\Struct\PaymentTransaction;
use Shopware\Payment\Token\PaymentTransactionTokenFactory;
use Shopware\Payment\Token\PaymentTransactionTokenFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

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
     * @var RouterInterface
     */
    private $router;

    /**
     * @var PaymentHandlerRegistry
     */
    private $paymentHandlerRegistry;

    public function __construct(
        PaymentTransactionTokenFactoryInterface $tokenFactory,
        OrderRepository $orderRepository,
        PaymentMethodRepository $paymentMethodRepository,
        RouterInterface $router,
        PaymentHandlerRegistry $paymentHandlerRegistry
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->orderRepository = $orderRepository;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->router = $router;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
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

        return $this->paymentHandlerRegistry->get($paymentMethod->getClass());
    }

    private function assembleReturnUrl(string $token): string
    {
        return $this->router->generate(
            'checkout_finalize_transaction',
            ['_sw_payment_token' => $token],
            RouterInterface::ABSOLUTE_URL
        );
    }
}
