<?php declare(strict_types=1);

namespace Shopware\Checkout\Payment\Cart;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Collection\OrderTransactionBasicCollection;
use Shopware\Checkout\Order\OrderRepository;
use Shopware\Checkout\Order\Struct\OrderDetailStruct;
use Shopware\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactory;
use Shopware\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactoryInterface;
use Shopware\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Checkout\Payment\PaymentMethodRepository;
use Shopware\Defaults;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PaymentTransactionChainProcessor
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
     * @param string             $orderId
     * @param ApplicationContext $context
     *
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     *
     * @return null|RedirectResponse
     */
    public function process(string $orderId, ApplicationContext $context): ?RedirectResponse
    {
        /** @var OrderDetailStruct $order */
        $order = $this->orderRepository->readDetail([$orderId], $context)->first();

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        /** @var OrderTransactionBasicCollection $transactions */
        $transactions = $order->getTransactions()->filterByOrderStateId(Defaults::ORDER_TRANSACTION_OPEN);

        foreach ($transactions as $transaction) {
            $token = $this->tokenFactory->generateToken($transaction, $context);

            $returnUrl = $this->assembleReturnUrl($token);

            $paymentTransaction = new PaymentTransactionStruct(
                $transaction->getId(),
                $transaction->getPaymentMethodId(),
                $order,
                $transaction->getAmount(),
                $returnUrl
            );

            $handler = $this->getPaymentHandlerById($transaction->getPaymentMethodId(), $context);

            $response = $handler->pay($paymentTransaction, $context);
            if ($response) {
                return $response;
            }
        }

        return null;
    }

    private function getPaymentHandlerById(string $paymentMethodId, ApplicationContext $context): PaymentHandlerInterface
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
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
