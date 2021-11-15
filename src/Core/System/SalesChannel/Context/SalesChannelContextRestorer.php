<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Exception\MissingOrderRelationException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundByIdException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelContextRestorer
{
    protected CartRestorer $cartRestorer;

    private AbstractSalesChannelContextFactory $factory;

    private CartRuleLoader $cartRuleLoader;

    private AbstractRuleLoader $ruleLoader;

    private OrderConverter $orderConverter;

    private EntityRepositoryInterface $orderRepository;

    private Connection $connection;

    public function __construct(
        AbstractSalesChannelContextFactory $factory,
        CartRuleLoader $cartRuleLoader,
        AbstractRuleLoader $ruleLoader,
        OrderConverter $orderConverter,
        EntityRepositoryInterface $orderRepository,
        Connection $connection,
        CartRestorer $cartRestorer
    ) {
        $this->factory = $factory;
        $this->cartRuleLoader = $cartRuleLoader;
        $this->ruleLoader = $ruleLoader;
        $this->orderConverter = $orderConverter;
        $this->orderRepository = $orderRepository;
        $this->connection = $connection;
        $this->cartRestorer = $cartRestorer;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function restoreByOrder(string $orderId, Context $context, array $overrideOptions = []): SalesChannelContext
    {
        $order = $this->getOrderById($orderId, $context);
        if ($order === null) {
            throw new OrderNotFoundException($orderId);
        }

        if ($order->getOrderCustomer() === null) {
            throw new MissingOrderRelationException('orderCustomer');
        }

        $customer = $order->getOrderCustomer()->getCustomer();
        $customerGroupId = null;
        if ($customer) {
            $customerGroupId = $customer->getGroupId();
        }

        $billingAddress = $order->getBillingAddress();
        $countryStateId = null;
        if ($billingAddress) {
            $countryStateId = $billingAddress->getCountryStateId();
        }

        $options = [
            SalesChannelContextService::CURRENCY_ID => $order->getCurrencyId(),
            SalesChannelContextService::LANGUAGE_ID => $order->getLanguageId(),
            SalesChannelContextService::CUSTOMER_ID => $order->getOrderCustomer()->getCustomerId(),
            SalesChannelContextService::COUNTRY_STATE_ID => $countryStateId,
            SalesChannelContextService::CUSTOMER_GROUP_ID => $customerGroupId,
            SalesChannelContextService::PERMISSIONS => OrderConverter::ADMIN_EDIT_ORDER_PERMISSIONS,
            SalesChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        if ($paymentMethodId = $this->getPaymentMethodId($order)) {
            $options[SalesChannelContextService::PAYMENT_METHOD_ID] = $paymentMethodId;
        }

        $delivery = $order->getDeliveries() !== null ? $order->getDeliveries()->first() : null;
        if ($delivery !== null) {
            $options[SalesChannelContextService::SHIPPING_METHOD_ID] = $delivery->getShippingMethodId();
        }

        $options = array_merge($options, $overrideOptions);

        $salesChannelContext = $this->factory->create(
            Uuid::randomHex(),
            $order->getSalesChannelId(),
            $options
        );

        $salesChannelContext->getContext()->addExtensions($context->getExtensions());
        $salesChannelContext->addState(...$context->getStates());

        if ($context->hasState(Context::SKIP_TRIGGER_FLOW)) {
            $salesChannelContext->getContext()->addState(Context::SKIP_TRIGGER_FLOW);
        }

        if ($order->getItemRounding() !== null) {
            $salesChannelContext->setItemRounding($order->getItemRounding());
        }

        if ($order->getTotalRounding() !== null) {
            $salesChannelContext->setTotalRounding($order->getTotalRounding());
        }

        $cart = $this->orderConverter->convertToCart($order, $salesChannelContext->getContext());
        $this->cartRuleLoader->loadByCart(
            $salesChannelContext,
            $cart,
            new CartBehavior($salesChannelContext->getPermissions()),
            true
        );

        return $salesChannelContext;
    }

    public function restoreByCustomer(string $customerId, Context $context, array $overrideOptions = []): SalesChannelContext
    {
        $customer = $this->connection->createQueryBuilder()
            ->select([
                'LOWER(HEX(language_id))',
                'LOWER(HEX(customer_group_id))',
                'LOWER(HEX(sales_channel_id))',
            ])
            ->from('customer')
            ->where('id = :id')
            ->setParameter('id', Uuid::fromHexToBytes($customerId))
            ->execute()
            ->fetch();

        if ($customer === null) {
            throw new CustomerNotFoundByIdException($customerId);
        }

        list($languageId, $groupId, $salesChannelId) = array_values($customer);
        $options = [
            SalesChannelContextService::LANGUAGE_ID => $languageId,
            SalesChannelContextService::CUSTOMER_ID => $customerId,
            SalesChannelContextService::CUSTOMER_GROUP_ID => $groupId,
            SalesChannelContextService::VERSION_ID => $context->getVersionId(),
        ];

        $options = array_merge($options, $overrideOptions);

        $salesChannelContext = $this->factory->create(
            Uuid::randomHex(),
            $salesChannelId,
            $options
        );

        $rules = $this->ruleLoader->load($context);
        $salesChannelContext->setRuleIds($rules->getIds());

        return $salesChannelContext;
    }

    /**
     * @deprecated tag:v6.5.0 - Use Shopware\Core\System\SalesChannel\Context\CartRestore::restore function instead
     */
    public function restore(string $customerId, SalesChannelContext $currentContext): SalesChannelContext
    {
        Feature::throwException('v6_5_0_0', 'Will be removed in v6.5.0');

        return $this->cartRestorer->restore($customerId, $currentContext);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderById(string $orderId, Context $context): ?OrderEntity
    {
        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('currency')
            ->addAssociation('deliveries')
            ->addAssociation('language.locale')
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('billingAddress')
            ->addAssociation('transactions');

        return $this->orderRepository->search($criteria, $context)
            ->get($orderId);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getPaymentMethodId(OrderEntity $order): ?string
    {
        $transactions = $order->getTransactions();
        if ($transactions === null) {
            throw new MissingOrderRelationException('transactions');
        }

        foreach ($transactions as $transaction) {
            if ($transaction->getStateMachineState() !== null
                && ($transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_CANCELLED
                    || $transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_FAILED)
            ) {
                continue;
            }

            return $transaction->getPaymentMethodId();
        }

        return $transactions->last() ? $transactions->last()->getPaymentMethodId() : null;
    }
}
