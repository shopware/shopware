<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\OrderStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\Token\PaymentTransactionTokenFactoryInterface;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PaymentTransactionChainProcessor
{
    /**
     * @var PaymentTransactionTokenFactoryInterface
     */
    private $tokenFactory;

    /**
     * @var RepositoryInterface
     */
    private $orderRepository;

    /**
     * @var RepositoryInterface
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
        RepositoryInterface $orderRepository,
        RepositoryInterface $paymentMethodRepository,
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
     * @param string  $orderId
     * @param Context $context
     *
     * @throws InvalidOrderException
     * @throws UnknownPaymentMethodException
     *
     * @return null|RedirectResponse
     */
    public function process(string $orderId, Context $context, ?string $finishUrl = null): ?RedirectResponse
    {
        /** @var OrderStruct $order */
        $criteria = new ReadCriteria([$orderId]);
        $criteria->addAssociation('order.transactions');

        $order = $this->orderRepository->read($criteria, $context)->first();

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        /** @var OrderTransactionCollection $transactions */
        $transactions = $order->getTransactions();
        $transactions = $transactions->filterByOrderStateId(Defaults::ORDER_TRANSACTION_OPEN);

        foreach ($transactions as $transaction) {
            $token = $this->tokenFactory->generateToken($transaction, $context);

            $returnUrl = $this->assembleReturnUrl($token, $finishUrl);

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
        $paymentMethods = $this->paymentMethodRepository->read(new ReadCriteria([$paymentMethodId]), $context);

        $paymentMethod = $paymentMethods->get($paymentMethodId);
        if (!$paymentMethod) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }

        return $this->paymentHandlerRegistry->get($paymentMethod->getClass());
    }

    private function assembleReturnUrl(string $token, ?string $finishUrl = null): string
    {
        $parameter = [
            '_sw_payment_token' => $token,
        ];

        // todo@ju when implementing transactions, finishUrl per transaction and per order is needed.

        if ($finishUrl) {
            $parameter['_sw_finish_url'] = $finishUrl;
        }

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
