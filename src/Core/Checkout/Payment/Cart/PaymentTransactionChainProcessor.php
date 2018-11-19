<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterface;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PaymentTransactionChainProcessor
{
    /**
     * @var TokenFactoryInterface
     */
    private $tokenFactory;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface
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
        TokenFactoryInterface $tokenFactory,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $paymentMethodRepository,
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
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function process(string $orderId, Context $context, ?string $finishUrl = null): ?RedirectResponse
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('order.transactions');

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $transactions = $order->getTransactions()->filterByState(Defaults::ORDER_TRANSACTION_STATES_OPEN);

        foreach ($transactions as $transaction) {
            $token = $this->tokenFactory->generateToken($transaction, $context, $finishUrl);
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

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerById(string $paymentMethodId, Context $context): PaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->search(new Criteria([$paymentMethodId]), $context);

        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->paymentHandlerRegistry->get($paymentMethod->getClass());
    }

    private function assembleReturnUrl(string $token): string
    {
        $parameter = [
            '_sw_payment_token' => $token,
        ];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
