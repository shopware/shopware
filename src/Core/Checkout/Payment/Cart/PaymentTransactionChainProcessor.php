<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\Exception\SyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

class PaymentTransactionChainProcessor
{
    private TokenFactoryInterfaceV2 $tokenFactory;

    private EntityRepositoryInterface $orderRepository;

    private RouterInterface $router;

    private PaymentHandlerRegistry $paymentHandlerRegistry;

    private StateMachineRegistry $stateMachineRegistry;

    public function __construct(
        TokenFactoryInterfaceV2 $tokenFactory,
        EntityRepositoryInterface $orderRepository,
        RouterInterface $router,
        PaymentHandlerRegistry $paymentHandlerRegistry,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->tokenFactory = $tokenFactory;
        $this->orderRepository = $orderRepository;
        $this->router = $router;
        $this->paymentHandlerRegistry = $paymentHandlerRegistry;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    /**
     * @throws AsyncPaymentProcessException
     * @throws InvalidOrderException
     * @throws SyncPaymentProcessException
     * @throws UnknownPaymentMethodException
     */
    public function process(
        string $orderId,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext,
        ?string $finishUrl = null,
        ?string $errorUrl = null
    ): ?RedirectResponse {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions.stateMachineState');
        $criteria->addAssociation('transactions.paymentMethod');
        $criteria->addAssociation('orderCustomer.customer');
        $criteria->addAssociation('orderCustomer.salutation');
        $criteria->addAssociation('transactions.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('language');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('lineItems');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$order) {
            throw new InvalidOrderException($orderId);
        }

        $transactions = $order->getTransactions();
        if ($transactions === null) {
            throw new InvalidOrderException($orderId);
        }

        $transactions = $transactions->filterByStateId(
            $this->stateMachineRegistry->getInitialState(
                OrderTransactionStates::STATE_MACHINE,
                $salesChannelContext->getContext()
            )->getId()
        );

        $transaction = $transactions->last();
        if ($transaction === null) {
            return null;
        }

        $paymentMethod = $transaction->getPaymentMethod();
        if ($paymentMethod === null) {
            throw new UnknownPaymentMethodException($transaction->getPaymentMethodId());
        }

        $paymentHandler = $this->paymentHandlerRegistry->getHandlerForPaymentMethod($paymentMethod);

        if (!$paymentHandler) {
            throw new UnknownPaymentMethodException($paymentMethod->getHandlerIdentifier());
        }

        if ($paymentHandler instanceof SynchronousPaymentHandlerInterface) {
            $paymentTransaction = new SyncPaymentTransactionStruct($transaction, $order);
            $paymentHandler->pay($paymentTransaction, $dataBag, $salesChannelContext);

            return null;
        }

        $tokenStruct = new TokenStruct(
            null,
            null,
            $transaction->getPaymentMethodId(),
            $transaction->getId(),
            $finishUrl,
            null,
            $errorUrl
        );

        $token = $this->tokenFactory->generateToken($tokenStruct);

        $returnUrl = $this->assembleReturnUrl($token);
        $paymentTransaction = new AsyncPaymentTransactionStruct($transaction, $order, $returnUrl);

        return $paymentHandler->pay($paymentTransaction, $dataBag, $salesChannelContext);
    }

    private function assembleReturnUrl(string $token): string
    {
        $parameter = ['_sw_payment_token' => $token];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
