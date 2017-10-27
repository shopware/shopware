<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\ExtensionRegistryInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderAddress\Factory\OrderAddressBasicFactory;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicStruct;
use Shopware\OrderDelivery\Struct\OrderDeliveryDetailStruct;
use Shopware\OrderDeliveryPosition\Factory\OrderDeliveryPositionBasicFactory;
use Shopware\OrderState\Factory\OrderStateBasicFactory;
use Shopware\ShippingMethod\Factory\ShippingMethodBasicFactory;

class OrderDeliveryDetailFactory extends OrderDeliveryBasicFactory
{
    /**
     * @var OrderDeliveryPositionBasicFactory
     */
    protected $orderDeliveryPositionFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        OrderDeliveryPositionBasicFactory $orderDeliveryPositionFactory,
        OrderStateBasicFactory $orderStateFactory,
        OrderAddressBasicFactory $orderAddressFactory,
        ShippingMethodBasicFactory $shippingMethodFactory
    ) {
        parent::__construct($connection, $registry, $orderStateFactory, $orderAddressFactory, $shippingMethodFactory);
        $this->orderDeliveryPositionFactory = $orderDeliveryPositionFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());

        return $fields;
    }

    public function hydrate(
        array $data,
        OrderDeliveryBasicStruct $orderDelivery,
        QuerySelection $selection,
        TranslationContext $context
    ): OrderDeliveryBasicStruct {
        /** @var OrderDeliveryDetailStruct $orderDelivery */
        $orderDelivery = parent::hydrate($data, $orderDelivery, $selection, $context);

        return $orderDelivery;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        $this->joinPositions($selection, $query, $context);
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['positions'] = $this->orderDeliveryPositionFactory->getAllFields();

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

    private function joinPositions(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
        if (!($positions = $selection->filter('positions'))) {
            return;
        }
        $query->leftJoin(
            $selection->getRootEscaped(),
            'order_delivery_position',
            $positions->getRootEscaped(),
            sprintf('%s.uuid = %s.order_delivery_uuid', $selection->getRootEscaped(), $positions->getRootEscaped())
        );

        $this->orderDeliveryPositionFactory->joinDependencies($positions, $query, $context);

        $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
    }
}
