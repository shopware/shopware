<?php

namespace Shopware\Holiday\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Holiday\Factory\HolidayBasicFactory;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\Holiday\Struct\HolidayBasicStruct;

class HolidayBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var HolidayBasicFactory
     */
    private $factory;

    public function __construct(
        HolidayBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        if (empty($uuids)) {
            return new HolidayBasicCollection();
        }

        $holidaies = $this->read($uuids, $context);

        return $holidaies;
    }

    private function read(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('holiday.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new HolidayBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new HolidayBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
