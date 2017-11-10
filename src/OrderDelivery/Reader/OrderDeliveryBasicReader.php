<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\OrderDelivery\Factory\OrderDeliveryBasicFactory;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicCollection;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicStruct;

class OrderDeliveryBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var OrderDeliveryBasicFactory
     */
    private $factory;

    public function __construct(
        OrderDeliveryBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): OrderDeliveryBasicCollection
    {
        if (empty($uuids)) {
            return new OrderDeliveryBasicCollection();
        }

        $orderDeliveriesCollection = $this->read($uuids, $context);

        return $orderDeliveriesCollection;
    }

    private function read(array $uuids, TranslationContext $context): OrderDeliveryBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('order_delivery.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new OrderDeliveryBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new OrderDeliveryBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
