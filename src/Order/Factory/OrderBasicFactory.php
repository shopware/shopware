<?php declare(strict_types=1);

namespace Shopware\Order\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyBasicFactory;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Customer\Factory\CustomerBasicFactory;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
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
       'order_date' => 'order_date',
       'customer_uuid' => 'customer_uuid',
       'amount_total' => 'amount_total',
       'position_price' => 'position_price',
       'shipping_total' => 'shipping_total',
       'order_state_uuid' => 'order_state_uuid',
       'payment_method_uuid' => 'payment_method_uuid',
       'is_net' => 'is_net',
       'is_tax_free' => 'is_tax_free',
       'currency_uuid' => 'currency_uuid',
       'shop_uuid' => 'shop_uuid',
       'billing_address_uuid' => 'billing_address_uuid',
       'context' => 'context',
       'payload' => 'payload',
       'created_at' => 'created_at',
       'updated_at' => 'updated_at',
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
        $order->setDate(new \DateTime($data[$selection->getField('order_date')]));
        $order->setCustomerUuid((string) $data[$selection->getField('customer_uuid')]);
        $order->setAmountTotal((float) $data[$selection->getField('amount_total')]);
        $order->setPositionPrice((float) $data[$selection->getField('position_price')]);
        $order->setShippingTotal((float) $data[$selection->getField('shipping_total')]);
        $order->setStateUuid((string) $data[$selection->getField('order_state_uuid')]);
        $order->setPaymentMethodUuid((string) $data[$selection->getField('payment_method_uuid')]);
        $order->setIsNet((bool) $data[$selection->getField('is_net')]);
        $order->setIsTaxFree((bool) $data[$selection->getField('is_tax_free')]);
        $order->setCurrencyUuid((string) $data[$selection->getField('currency_uuid')]);
        $order->setShopUuid((string) $data[$selection->getField('shop_uuid')]);
        $order->setBillingAddressUuid((string) $data[$selection->getField('billing_address_uuid')]);
        $order->setContext((string) $data[$selection->getField('context')]);
        $order->setPayload((string) $data[$selection->getField('payload')]);
        $order->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('created_at')]) : null);
        $order->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updated_at')]) : null);
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
        if ($customer = $selection->filter('customer')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'customer',
                $customer->getRootEscaped(),
                sprintf('%s.uuid = %s.customer_uuid', $customer->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->customerFactory->joinDependencies($customer, $query, $context);
        }

        if ($orderState = $selection->filter('state')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'order_state',
                $orderState->getRootEscaped(),
                sprintf('%s.uuid = %s.order_state_uuid', $orderState->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->orderStateFactory->joinDependencies($orderState, $query, $context);
        }

        if ($paymentMethod = $selection->filter('paymentMethod')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'payment_method',
                $paymentMethod->getRootEscaped(),
                sprintf('%s.uuid = %s.payment_method_uuid', $paymentMethod->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->paymentMethodFactory->joinDependencies($paymentMethod, $query, $context);
        }

        if ($currency = $selection->filter('currency')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'currency',
                $currency->getRootEscaped(),
                sprintf('%s.uuid = %s.currency_uuid', $currency->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->currencyFactory->joinDependencies($currency, $query, $context);
        }

        if ($shop = $selection->filter('shop')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'shop',
                $shop->getRootEscaped(),
                sprintf('%s.uuid = %s.shop_uuid', $shop->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->shopFactory->joinDependencies($shop, $query, $context);
        }

        if ($orderAddress = $selection->filter('billingAddress')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'order_address',
                $orderAddress->getRootEscaped(),
                sprintf('%s.uuid = %s.billing_address_uuid', $orderAddress->getRootEscaped(), $selection->getRootEscaped())
            );
            $this->orderAddressFactory->joinDependencies($orderAddress, $query, $context);
        }

        if ($translation = $selection->filter('translation')) {
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
}
