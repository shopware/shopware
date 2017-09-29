<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Framework\Factory\Factory;
use Shopware\OrderAddress\Factory\OrderAddressBasicFactory;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\OrderDelivery\Extension\OrderDeliveryExtension;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicStruct;
use Shopware\OrderState\Factory\OrderStateBasicFactory;
use Shopware\OrderState\Struct\OrderStateBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\ShippingMethod\Factory\ShippingMethodBasicFactory;
use Shopware\ShippingMethod\Struct\ShippingMethodBasicStruct;

class OrderDeliveryBasicFactory extends Factory
{
    const ROOT_NAME = 'order_delivery';
    const EXTENSION_NAMESPACE = 'orderDelivery';

    const FIELDS = [
       'uuid' => 'uuid',
       'orderUuid' => 'order_uuid',
       'shippingAddressUuid' => 'shipping_address_uuid',
       'orderStateUuid' => 'order_state_uuid',
       'trackingCode' => 'tracking_code',
       'shippingMethodUuid' => 'shipping_method_uuid',
       'shippingDateEarliest' => 'shipping_date_earliest',
       'shippingDateLatest' => 'shipping_date_latest',
       'payload' => 'payload',
       'createdAt' => 'created_at',
       'updatedAt' => 'updated_at',
    ];

    /**
     * @var OrderStateBasicFactory
     */
    protected $orderStateFactory;

    /**
     * @var OrderAddressBasicFactory
     */
    protected $orderAddressFactory;

    /**
     * @var ShippingMethodBasicFactory
     */
    protected $shippingMethodFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        OrderStateBasicFactory $orderStateFactory,
        OrderAddressBasicFactory $orderAddressFactory,
        ShippingMethodBasicFactory $shippingMethodFactory
    ) {
        parent::__construct($connection, $registry);
        $this->orderStateFactory = $orderStateFactory;
        $this->orderAddressFactory = $orderAddressFactory;
        $this->shippingMethodFactory = $shippingMethodFactory;
    }

    public function hydrate(
        array $data,
        OrderDeliveryBasicStruct $orderDelivery,
        QuerySelection $selection,
        TranslationContext $context
    ): OrderDeliveryBasicStruct {
        $orderDelivery->setUuid((string) $data[$selection->getField('uuid')]);
        $orderDelivery->setOrderUuid((string) $data[$selection->getField('orderUuid')]);
        $orderDelivery->setShippingAddressUuid((string) $data[$selection->getField('shippingAddressUuid')]);
        $orderDelivery->setOrderStateUuid((string) $data[$selection->getField('orderStateUuid')]);
        $orderDelivery->setTrackingCode(isset($data[$selection->getField('tracking_code')]) ? (string) $data[$selection->getField('trackingCode')] : null);
        $orderDelivery->setShippingMethodUuid((string) $data[$selection->getField('shippingMethodUuid')]);
        $orderDelivery->setShippingDateEarliest(new \DateTime($data[$selection->getField('shippingDateEarliest')]));
        $orderDelivery->setShippingDateLatest(new \DateTime($data[$selection->getField('shippingDateLatest')]));
        $orderDelivery->setPayload((string) $data[$selection->getField('payload')]);
        $orderDelivery->setCreatedAt(isset($data[$selection->getField('created_at')]) ? new \DateTime($data[$selection->getField('createdAt')]) : null);
        $orderDelivery->setUpdatedAt(isset($data[$selection->getField('updated_at')]) ? new \DateTime($data[$selection->getField('updatedAt')]) : null);
        $orderState = $selection->filter('state');
        if ($orderState && !empty($data[$orderState->getField('uuid')])) {
            $orderDelivery->setState(
                $this->orderStateFactory->hydrate($data, new OrderStateBasicStruct(), $orderState, $context)
            );
        }
        $orderAddress = $selection->filter('shippingAddress');
        if ($orderAddress && !empty($data[$orderAddress->getField('uuid')])) {
            $orderDelivery->setShippingAddress(
                $this->orderAddressFactory->hydrate($data, new OrderAddressBasicStruct(), $orderAddress, $context)
            );
        }
        $shippingMethod = $selection->filter('shippingMethod');
        if ($shippingMethod && !empty($data[$shippingMethod->getField('uuid')])) {
            $orderDelivery->setShippingMethod(
                $this->shippingMethodFactory->hydrate($data, new ShippingMethodBasicStruct(), $shippingMethod, $context)
            );
        }

        /** @var $extension OrderDeliveryExtension */
        foreach ($this->getExtensions() as $extension) {
            $extension->hydrate($orderDelivery, $data, $selection, $context);
        }

        return $orderDelivery;
    }

    public function getFields(): array
    {
        $fields = array_merge(self::FIELDS, parent::getFields());

        $fields['state'] = $this->orderStateFactory->getFields();
        $fields['shippingAddress'] = $this->orderAddressFactory->getFields();
        $fields['shippingMethod'] = $this->shippingMethodFactory->getFields();

        return $fields;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        $this->joinState($selection, $query, $context);
        $this->joinShippingAddress($selection, $query, $context);
        $this->joinShippingMethod($selection, $query, $context);
        $this->joinTranslation($selection, $query, $context);

        $this->joinExtensionDependencies($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = array_merge(self::FIELDS, $this->getExtensionFields());
        $fields['state'] = $this->orderStateFactory->getAllFields();
        $fields['shippingAddress'] = $this->orderAddressFactory->getAllFields();
        $fields['shippingMethod'] = $this->shippingMethodFactory->getAllFields();

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

    private function joinShippingAddress(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($orderAddress = $selection->filter('shippingAddress'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'order_address',
            $orderAddress->getRootEscaped(),
            sprintf('%s.uuid = %s.shipping_address_uuid', $orderAddress->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->orderAddressFactory->joinDependencies($orderAddress, $query, $context);
    }

    private function joinShippingMethod(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($shippingMethod = $selection->filter('shippingMethod'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'shipping_method',
            $shippingMethod->getRootEscaped(),
            sprintf('%s.uuid = %s.shipping_method_uuid', $shippingMethod->getRootEscaped(), $selection->getRootEscaped())
        );
        $this->shippingMethodFactory->joinDependencies($shippingMethod, $query, $context);
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
            'order_delivery_translation',
            $translation->getRootEscaped(),
            sprintf(
                '%s.order_delivery_uuid = %s.uuid AND %s.language_uuid = :languageUuid',
                $translation->getRootEscaped(),
                $selection->getRootEscaped(),
                $translation->getRootEscaped()
            )
        );
        $query->setParameter('languageUuid', $context->getShopUuid());
    }
}
