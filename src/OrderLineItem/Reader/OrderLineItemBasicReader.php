<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\OrderLineItem\Factory\OrderLineItemBasicFactory;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicCollection;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicStruct;

class OrderLineItemBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var OrderLineItemBasicFactory
     */
    private $factory;

    public function __construct(
        OrderLineItemBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): OrderLineItemBasicCollection
    {
        if (empty($uuids)) {
            return new OrderLineItemBasicCollection();
        }

        $orderLineItemsCollection = $this->read($uuids, $context);

        return $orderLineItemsCollection;
    }

    private function read(array $uuids, TranslationContext $context): OrderLineItemBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('order_line_item.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new OrderLineItemBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new OrderLineItemBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
