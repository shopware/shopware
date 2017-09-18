<?php

namespace Shopware\ListingSorting\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ListingSorting\Factory\ListingSortingBasicFactory;
use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;

class ListingSortingBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ListingSortingBasicFactory
     */
    private $factory;

    public function __construct(
        ListingSortingBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ListingSortingBasicCollection
    {
        $listingSortings = $this->read($uuids, $context);

        return $listingSortings;
    }

    private function read(array $uuids, TranslationContext $context): ListingSortingBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('listing_sorting.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ListingSortingBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ListingSortingBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
