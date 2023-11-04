<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Exception\StateMachineStateNotFoundException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Validator\Constraints\NotBlank;

#[Package('customer-order')]
class OrderService
{
    final public const CUSTOMER_COMMENT_KEY = 'customerComment';
    final public const AFFILIATE_CODE_KEY = 'affiliateCode';
    final public const CAMPAIGN_CODE_KEY = 'campaignCode';

    final public const ALLOWED_TRANSACTION_STATES = [
        OrderTransactionStates::STATE_OPEN,
        OrderTransactionStates::STATE_CANCELLED,
        OrderTransactionStates::STATE_REMINDED,
        OrderTransactionStates::STATE_FAILED,
        OrderTransactionStates::STATE_CHARGEBACK,
        OrderTransactionStates::STATE_UNCONFIRMED,
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly DataValidator $dataValidator,
        private readonly DataValidationFactoryInterface $orderValidationFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartService $cartService,
        private readonly EntityRepository $paymentMethodRepository,
        private readonly StateMachineRegistry $stateMachineRegistry
    ) {
    }

    /**
     * @throws ConstraintViolationException
     */
    public function createOrder(DataBag $data, SalesChannelContext $context): string
    {
        $cart = $this->cartService->getCart($context->getToken(), $context);

        $this->validateOrderData($data, $context, $cart->getLineItems()->hasLineItemWithState(State::IS_DOWNLOAD));

        $this->validateCart($cart, $context->getContext());

        return $this->cartService->order($cart, $context, $data->toRequestDataBag());
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderStateTransition(
        string $orderId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order',
                $orderId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            throw new StateMachineStateNotFoundException('order_transaction', $transition);
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderTransactionStateTransition(
        string $orderTransactionId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_transaction',
                $orderTransactionId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            throw new StateMachineStateNotFoundException('order_transaction', $transition);
        }

        return $toPlace;
    }

    /**
     * @internal Should not be called from outside the core
     */
    public function orderDeliveryStateTransition(
        string $orderDeliveryId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): StateMachineStateEntity {
        $stateFieldName = $data->get('stateFieldName', 'stateId');

        $stateMachineStates = $this->stateMachineRegistry->transition(
            new Transition(
                'order_delivery',
                $orderDeliveryId,
                $transition,
                $stateFieldName
            ),
            $context
        );

        $toPlace = $stateMachineStates->get('toPlace');

        if (!$toPlace) {
            throw new StateMachineStateNotFoundException('order_transaction', $transition);
        }

        return $toPlace;
    }

    public function isPaymentChangeableByTransactionState(OrderEntity $order): bool
    {
        if ($order->getTransactions() === null) {
            return true;
        }

        $transaction = $order->getTransactions()->last();

        if ($transaction === null || $transaction->getStateMachineState() === null) {
            return true;
        }

        $state = $transaction->getStateMachineState()->getTechnicalName();

        if (\in_array($state, self::ALLOWED_TRANSACTION_STATES, true)) {
            return true;
        }

        return false;
    }

    private function validateCart(Cart $cart, Context $context): void
    {
        $idsOfPaymentMethods = [];

        foreach ($cart->getTransactions() as $paymentMethod) {
            $idsOfPaymentMethods[] = $paymentMethod->getPaymentMethodId();
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('active', true)
        );

        $paymentMethods = $this->paymentMethodRepository->searchIds($criteria, $context);

        if ($paymentMethods->getTotal() !== \count(array_unique($idsOfPaymentMethods))) {
            foreach ($cart->getTransactions() as $paymentMethod) {
                if (!\in_array($paymentMethod->getPaymentMethodId(), $paymentMethods->getIds(), true)) {
                    throw new PaymentMethodNotAvailableException($paymentMethod->getPaymentMethodId());
                }
            }
        }
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateOrderData(
        ParameterBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): void {
        $definition = $this->getOrderCreateValidationDefinition(new DataBag($data->all()), $context, $hasVirtualGoods);
        $violations = $this->dataValidator->getViolations($data->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $data->all());
        }
    }

    private function getOrderCreateValidationDefinition(
        DataBag $data,
        SalesChannelContext $context,
        bool $hasVirtualGoods
    ): DataValidationDefinition {
        $validation = $this->orderValidationFactory->create($context);

        if ($hasVirtualGoods) {
            $validation->add('revocation', new NotBlank());
        }

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }
}
