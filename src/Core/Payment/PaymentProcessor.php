<?php declare(strict_types=1);

namespace Shopware\Payment;

use Shopware\Checkout\Order\Collection\OrderTransactionBasicCollection;
use Shopware\Checkout\Order\Repository\OrderRepository;
use Shopware\Checkout\Order\Struct\OrderDetailStruct;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\Payment\Exception\InvalidOrderException;
use Shopware\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Payment\PaymentHandler\PaymentHandlerInterface;
use Shopware\Payment\Struct\PaymentTransaction;
use Shopware\Payment\Token\PaymentTransactionTokenFactory;
use Shopware\Payment\Token\PaymentTransactionTokenFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

            $paymentTransaction = new PaymentTransaction(
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
