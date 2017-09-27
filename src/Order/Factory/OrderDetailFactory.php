<?php declare(strict_types=1);

namespace Shopware\Order\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyBasicFactory;
use Shopware\Customer\Factory\CustomerBasicFactory;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Order\Struct\OrderBasicStruct;
use Shopware\Order\Struct\OrderDetailStruct;
use Shopware\OrderAddress\Factory\OrderAddressBasicFactory;
use Shopware\OrderDelivery\Factory\OrderDeliveryDetailFactory;
use Shopware\OrderLineItem\Factory\OrderLineItemBasicFactory;
use Shopware\OrderState\Factory\OrderStateBasicFactory;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Factory\ShopBasicFactory;

class OrderDetailFactory extends OrderBasicFactory
{
    /**
     * @var OrderLineItemBasicFactory
     */
    protected $orderLineItemFactory;

    /**
     * @var OrderDeliveryDetailFactory
     */
    protected $orderDeliveryFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        OrderLineItemBasicFactory $orderLineItemFactory,
        OrderDeliveryDetailFactory $orderDeliveryFactory,
        CustomerBasicFactory $customerFactory,
        OrderStateBasicFactory $orderStateFactory,
        PaymentMethodBasicFactory $paymentMethodFactory,
        CurrencyBasicFactory $currencyFactory,
        ShopBasicFactory $shopFactory,
        OrderAddressBasicFactory $orderAddressFactory
    ) {
        parent::__construct($connection, $registry, $customerFactory, $orderStateFactory, $paymentMethodFactory, $currencyFactory, $shopFactory, $orderAddressFactory);
        $this->orderLineItemFactory = $orderLineItemFactory;
        $this->orderDeliveryFactory = $orderDeliveryFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        OrderBasicStruct $order,
        QuerySelection $selection,
        TranslationContext $context
    ): OrderBasicStruct {
        /** @var OrderDetailStruct $order */
        $order = parent::hydrate($data, $order, $selection, $context);

        return $order;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($lineItems = $selection->filter('lineItems')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'order_line_item',
                $lineItems->getRootEscaped(),
                sprintf('%s.uuid = %s.order_uuid', $selection->getRootEscaped(), $lineItems->getRootEscaped())
            );

            $this->orderLineItemFactory->joinDependencies($lineItems, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($deliveries = $selection->filter('deliveries')) {
            $query->leftJoin(
                $selection->getRootEscaped(),
                'order_delivery',
                $deliveries->getRootEscaped(),
                sprintf('%s.uuid = %s.order_uuid', $selection->getRootEscaped(), $deliveries->getRootEscaped())
            );

            $this->orderDeliveryFactory->joinDependencies($deliveries, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['lineItems'] = $this->orderLineItemFactory->getAllFields();
        $fields['deliveries'] = $this->orderDeliveryFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
