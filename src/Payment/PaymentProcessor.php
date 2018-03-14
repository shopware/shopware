<?php declare(strict_types=1);

namespace Shopware\Payment;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Order\Repository\OrderRepository;
use Shopware\Api\Order\Struct\OrderBasicStruct;
use Shopware\Api\Order\Struct\OrderDetailStruct;
use Shopware\Api\Order\Struct\OrderTransactionBasicStruct;
use Shopware\Api\Payment\Repository\PaymentMethodRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Framework\Routing\Router;
use Shopware\Payment\Exception\InvalidOrderException;
use Shopware\Payment\Exception\InvalidTransactionException;
use Shopware\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Payment\PaymentHandler\PaymentHandlerInterface;
use Shopware\Payment\Struct\PaymentTransaction;
use Shopware\Payment\Token\TokenFactory;
use Shopware\Payment\Token\TokenFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class PaymentProcessor
{
    /**
     * @var TokenFactory
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

    public function __construct(
        TokenFactoryInterface $tokenFactory,
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
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     */
    public function process(string $orderId, ShopContext $shopContext): ?RedirectResponse
    {
        /** @var OrderDetailStruct $order */
        $order = $this->orderRepository->readDetail([$orderId], $shopContext)->first();

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $transactions = $order->getTransactions()->filterByOrderStateId(Defaults::ORDER_TRANSACTION_OPEN);

        /** @var OrderTransactionBasicStruct $transaction */
        foreach ($transactions as $transaction) {
            $token = $this->tokenFactory->generateToken($transaction->getPaymentMethodId(), $transaction->getId());
            $returnUrl = $this->assembleReturnUrl($token);
            $paymentTransaction = new PaymentTransaction(
                $transaction->getId(),
                $order,
                $transaction->getAmount(),
                $returnUrl
            );
            $handler = $this->getPaymentHandlerById($transaction->getPaymentMethodId(), $shopContext);

            if ($response = $handler->payAction($paymentTransaction, $shopContext)) {
                return $response;
            }
        }

        return null;
    }

    /**
     * @throws InvalidTransactionException
     */
    public function getOrderByTransactionId(string $transactionId, string $customerId, ShopContext $context): OrderBasicStruct
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('order.customer.id', $customerId));
        $criteria->addFilter(new TermQuery('order.transactions.id', $transactionId));

        $searchResult = $this->orderRepository->search($criteria, $context);

        if ($searchResult->count() !== 1) {
            throw new InvalidTransactionException($transactionId);
        }

        return $searchResult->first();
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerByClass(string $paymentHandlerClass): PaymentHandlerInterface
    {
        try {
            return $this->container->get($paymentHandlerClass);
        } catch (NotFoundExceptionInterface $e) {
            throw new UnknownPaymentMethodException($paymentHandlerClass);
        }
    }

    /**
     * @throws UnknownPaymentMethodException
     */
    private function getPaymentHandlerById(string $paymentHandlerId, ShopContext $context): PaymentHandlerInterface
    {
        $paymentMethods = $this->paymentMethodRepository->readBasic([$paymentHandlerId], $context);

        if ($paymentMethods->count() !== 1) {
            throw new UnknownPaymentMethodException($paymentHandlerId);
        }

        return $this->getPaymentHandlerByClass($paymentMethods->first()->getClass());
    }

    private function assembleReturnUrl(string $token): string
    {
        return $this->router->generate(
            'payment_finalize',
            ['token' => $token],
            Router::ABSOLUTE_URL
        );
    }
}
