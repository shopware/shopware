<?php declare(strict_types=1);

namespace Shopware\Order\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Order\Factory\OrderBasicFactory;
use Shopware\Order\Struct\OrderBasicCollection;
use Shopware\Order\Struct\OrderBasicStruct;

class OrderBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var OrderBasicFactory
     */
    private $factory;

    public function __construct(
        OrderBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): OrderBasicCollection
    {
        if (empty($uuids)) {
            return new OrderBasicCollection();
        }

        $ordersCollection = $this->read($uuids, $context);

        return $ordersCollection;
    }

    private function read(array $uuids, TranslationContext $context): OrderBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('order.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new OrderBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new OrderBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
