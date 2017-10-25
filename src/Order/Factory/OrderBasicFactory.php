<?php declare(strict_types=1);

namespace Shopware\Order\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyBasicFactory;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Customer\Factory\CustomerBasicFactory;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Read\ExtensionRegistryInterface;
use Shopware\Framework\Read\Factory;
use Shopware\Order\Extension\OrderExtension;
use Shopware\Order\Struct\OrderBasicStruct;
use Shopware\OrderAddress\Factory\OrderAddressBasicFactory;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\OrderState\Factory\OrderStateBasicFactory;
use Shopware\OrderState\Struct\OrderStateBasicStruct;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Factory\ShopBasicFactory;
use Shopware\Shop\Struct\ShopBasicStruct;

class OrderBasicFactory extends Factory
{
    const ROOT_NAME = 'order';
    const EXTENSION_NAMESPACE = 'order';

    const FIELDS = [
       'uuid' => 'uuid',
       'date' => 'order_date',
       'customerUuid' => 'customer_uuid',
       'amountTotal' => 'amount_total',
       'positionPrice' => 'position_price',
       'shippingTotal' => 'shipping_total',
       'stateUuid' => 'order_state_uuid',
       'paymentMethodUuid' => 'payment_method_uuid',
       'isNet' => 'is_net',
       'isTaxFree' => 'is_tax_free',
       'currencyUuid' => 'currency_uuid',
       'shopUuid' => 'shop_uuid',
       'billingAddressUuid' => 'billing_address_uuid',
       'context' => 'context',
       'payload' => 'payload',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
    ];

    /**
     * @var CustomerBasicFactory
     */
    protected $customerFactory;

    /**
     * @var OrderStateBasicFactory
     */
    protected $orderStateFactory;

    /**
     * @var PaymentMethodBasicFactory
     */
    protected $paymentMethodFactory;

    /**
     * @var CurrencyBasicFactory
     */
    protected $currencyFactory;

    /**
     * @var ShopBasicFactory
     */
    protected $shopFactory;

    /**
     * @var OrderAddressBasicFactory
     */
    protected $orderAddressFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        CustomerBasicFactory $customerFactory,
        OrderStateBasicFactory $orderStateFactory,
        PaymentMethodBasicFactory $paymentMethodFactory,
        CurrencyBasicFactory $currencyFactory,
        ShopBasicFactory $shopFactory,
        OrderAddressBasicFactory $orderAddressFactory
    ) {
        parent::__construct($connection, $registry);
        $this->customerFactory = $customerFactory;
        $this->orderStateFactory = $orderStateFactory;
        $this->paymentMethodFactory = $paymentMethodFactory;
        $this->currencyFactory = $currencyFactory;
        $this->shopFactory = $shopFactory;
        $this->orderAddressFactory = $orderAddressFactory;
    }

    public function hydrate(
        array $data,
        OrderBasicStruct $order,
        QuerySelection $selection,
        TranslationContext $context
    ): OrderBasicStruct {
        $order->setUuid((string) $data[$selection->getField('uuid')]);
        $order->setDate(new \DateTime($data[$selection->getField('date')]));
        $order->setCustomerUuid((string) $data[$selection->getField('customerUuid')]);
        $order->setAmountTotal((float) $data[$selection->getField('amountTotal')]);
        $order->setPositionPrice((float) $data[$selection->getField('positionPrice')]);
        $order->setShippingTotal((float) $data[$selection->getField('shippingTotal')]);
        $order->setStateUuid((string) $data[$selection->getField('stateUuid')]);
        $order->setPaymentMethodUuid((string) $data[$selection->getField('paymentMethodUuid')]);
        $order->setIsNet((bool) $data[$selection->getField('isNet')]);
        $order->setIsTaxFree((bool) $data[$selection->getField('isTaxFree')]);
        $order->setCurrencyUuid((string) $data[$selection->getField('currencyUuid')]);
        $order->setShopUuid((string) $data[$selection->getField('shopUuid')]);
        $order->setBillingAddressUuid((string) $data[$selection->getField('billingAddressUuid')]);
        $order->setContext((string) $data[$selection->getField('context')]);
        $order->setPayload((string) $data[$selection->getField('payload')]);
        $order->setCreatedAt(isset($data[$selection->getField('createdAt')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $order->setUpdatedAt(isset($data[$selection->getField('updatedAt')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $customer = $selection->filter('customer');
        if ($customer && !empty($data[$customer->getField('uuid')])) {
            $order->setCustomer(
                $this->customerFactory->hydrate($data, new CustomerBasicStruct(), $customer, $context)
            );
        }
        $orderState = $selection->filter('state');
        if ($orderState && !empty($data[$orderState->getField('uuid')])) {
            $order->setState(
                $this->orderStateFactory->hydrate($data, new OrderStateBasicStruct(), $orderState, $context)
            );
        }
        $paymentMethod = $selection->filter('paymentMethod');
        if ($paymentMethod && !empty($data[$paymentMethod->getField('uuid')])) {
            $order->setPaymentMethod(
                $this->paymentMethodFactory->hydrate($data, new PaymentMethodBasicStruct(), $paymentMethod, $context)
            );
        }
        $currency = $selection->filter('currency');
        if ($currency && !empty($data[$currency->getField('uuid')])) {
            $order->setCurrency(
                $this->currencyFactory->hydrate($data, new CurrencyBasicStruct(), $currency, $context)
            );
        }
        $shop = $selection->filter('shop');
        if ($shop && !empty($data[$shop->getField('uuid')])) {
            $order->setShop(
                $this->shopFactory->hydrate($data, new ShopBasicStruct(), $shop, $context)
            );
        }
        $orderAddress = $selection->filter('billingAddress');
        if ($orderAddress && !empty($data[$orderAddress->getField('uuid')])) {
            $order->setBillingAddress(
                $this->orderAddressFactory->hydrate($data, new OrderAddressBasicStruct(), $orderAddress, $context)
            );
        }

        /** @var $extension OrderExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($order, $data, $selection, $context);
        }

        return $order;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['customer'] = $this->customerFactory->getFields();
        $fields['state'] = $this->orderStateFactory->getFields();
        $fields['paymentMethod'] = $this->paymentMethodFactory->getFields();
        $fields['currency'] = $this->currencyFactory->getFields();
        $fields['shop'] = $this->shopFactory->getFields();
        $fields['billingAddress'] = $this->orderAddressFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinCustomer($selection, $query, $context);
        $this->joinState($selection, $query, $context);
        $this->joinPaymentMethod($selection, $query, $context);
        $this->joinCurrency($selection, $query, $context);
        $this->joinShop($selection, $query, $context);
        $this->joinBillingAddress($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['customer'] = $this->customerFactory->getAllFields();
        $fields['state'] = $this->orderStateFactory->getAllFields();
        $fields['paymentMethod'] = $this->paymentMethodFactory->getAllFields();
        $fields['currency'] = $this->currencyFactory->getAllFields();
        $fields['shop'] = $this->shopFactory->getAllFields();
        $fields['billingAddress'] = $this->orderAddressFactory->getAllFields();

        return $fields;
    }

    protected function getRootName(): string
    {
        return self::ROOT_NAME;
    }

    protected function getExtensionNamespace(): string
    {
        return self::EXTENSION_NAMESPACE;
    }

    private function joinCustomer(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($customer = $selection->filter('customer'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'customer',
            $customer->getRootEscaped(),
            sprintf('%s.uuid = %s.customer_uuid', $customer->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->customerFactory->joinDependencies($customer, $query, $context);
    }

    private function joinState(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($orderState = $selection->filter('state'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'order_state',
            $orderState->getRootEscaped(),
            sprintf('%s.uuid = %s.order_state_uuid', $orderState->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->orderStateFactory->joinDependencies($orderState, $query, $context);
    }

    private function joinPaymentMethod(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($paymentMethod = $selection->filter('paymentMethod'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'payment_method',
            $paymentMethod->getRootEscaped(),
            sprintf('%s.uuid = %s.payment_method_uuid', $paymentMethod->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->paymentMethodFactory->joinDependencies($paymentMethod, $query, $context);
    }

    private function joinCurrency(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($currency = $selection->filter('currency'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'currency',
            $currency->getRootEscaped(),
            sprintf('%s.uuid = %s.currency_uuid', $currency->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->currencyFactory->joinDependencies($currency, $query, $context);
    }

    private function joinShop(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($shop = $selection->filter('shop'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'shop',
            $shop->getRootEscaped(),
            sprintf('%s.uuid = %s.shop_uuid', $shop->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->shopFactory->joinDependencies($shop, $query, $context);
    }

    private function joinBillingAddress(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($orderAddress = $selection->filter('billingAddress'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'order_address',
            $orderAddress->getRootEscaped(),
            sprintf('%s.uuid = %s.billing_address_uuid', $orderAddress->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->orderAddressFactory->joinDependencies($orderAddress, $query, $context);
    }

    private function joinTranslation(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($translation = $selection->filter('translation'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'order_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.order_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
