<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerRegistry;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenFactoryInterfaceV2;
use Shopware\Core\Checkout\Payment\Cart\Token\TokenStruct;
use Shopware\Core\Checkout\Payment\Event\PayPaymentOrderCriteriaEvent;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * @deprecated tag:v6.7.0 - will be removed, use `PaymentProcessor` instead
 */
#[Package('checkout')]
class PaymentTransactionChainProcessor
{
    /**
     * @internal
     */
    public function __construct(
        private readonly TokenFactoryInterfaceV2 $tokenFactory,
        private readonly EntityRepository $orderRepository,
        private readonly RouterInterface $router,
        private readonly PaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly SystemConfigService $systemConfigService,
        private readonly InitialStateIdLoader $initialStateIdLoader,
        private readonly AbstractPaymentTransactionStructFactory $paymentTransactionStructFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function process(
        string $orderId,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext,
        ?string $finishUrl = null,
        ?string $errorUrl = null
    ): ?RedirectResponse {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            'The payment process via interfaces is deprecated, extend the `AbstractPaymentHandler` instead',
        );

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

        $this->eventDispatcher->dispatch(new PayPaymentOrderCriteriaEvent($orderId, $criteria, $salesChannelContext));

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();

        if (!$order) {
            throw PaymentException::invalidOrder($orderId);
        }

        $transactions = $order->getTransactions();
        if ($transactions === null) {
            throw PaymentException::invalidOrder($orderId);
        }

        $transactions = $transactions->filterByStateId(
            $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE)
        );

        $transaction = $transactions->last();
        if ($transaction === null) {
            return null;
        }

        $paymentHandler = $this->paymentHandlerRegistry->getPaymentMethodHandler($transaction->getPaymentMethodId());
        if (!$paymentHandler) {
            throw PaymentException::unknownPaymentMethodById($transaction->getPaymentMethodId());
        }

        if ($paymentHandler instanceof SynchronousPaymentHandlerInterface) {
            $paymentTransaction = $this->paymentTransactionStructFactory->sync($transaction, $order);
            $paymentHandler->pay($paymentTransaction, $dataBag, $salesChannelContext);

            return null;
        }

        if ($paymentHandler instanceof AsynchronousPaymentHandlerInterface) {
            $paymentFinalizeTransactionTime = $this->systemConfigService->get('core.cart.paymentFinalizeTransactionTime', $salesChannelContext->getSalesChannelId());

            if (\is_numeric($paymentFinalizeTransactionTime)) {
                $paymentFinalizeTransactionTime = (int) $paymentFinalizeTransactionTime;
                // setting is in minutes, token holds in seconds
                $paymentFinalizeTransactionTime *= 60;
            } else {
                $paymentFinalizeTransactionTime = null;
            }

            $tokenStruct = new TokenStruct(
                null,
                null,
                $transaction->getPaymentMethodId(),
                $transaction->getId(),
                $finishUrl,
                $paymentFinalizeTransactionTime,
                $errorUrl
            );

            $token = $this->tokenFactory->generateToken($tokenStruct);

            $returnUrl = $this->assembleReturnUrl($token);
            $paymentTransaction = $this->paymentTransactionStructFactory->async($transaction, $order, $returnUrl);

            return $paymentHandler->pay($paymentTransaction, $dataBag, $salesChannelContext);
        }

        return null;
    }

    private function assembleReturnUrl(string $token): string
    {
        $parameter = ['_sw_payment_token' => $token];

        return $this->router->generate('payment.finalize.transaction', $parameter, UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
