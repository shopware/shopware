<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ShippingMethodPrice\Factory\ShippingMethodPriceBasicFactory;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicStruct;

class ShippingMethodPriceBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ShippingMethodPriceBasicFactory
     */
    private $factory;

    public function __construct(
        ShippingMethodPriceBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        if (empty($uuids)) {
            return new ShippingMethodPriceBasicCollection();
        }

        $shippingMethodPricesCollection = $this->read($uuids, $context);

        return $shippingMethodPricesCollection;
    }

    private function read(array $uuids, TranslationContext $context): ShippingMethodPriceBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('shipping_method_price.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ShippingMethodPriceBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ShippingMethodPriceBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
