<?php

namespace Shopware\Area\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Area\Factory\AreaBasicFactory;
use Shopware\Area\Struct\AreaBasicCollection;
use Shopware\Area\Struct\AreaBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class AreaBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var AreaBasicFactory
     */
    private $factory;

    public function __construct(
        AreaBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): AreaBasicCollection
    {
        if (empty($uuids)) {
            return new AreaBasicCollection();
        }

        $areas = $this->read($uuids, $context);

        return $areas;
    }

    private function read(array $uuids, TranslationContext $context): AreaBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('area.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AreaBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AreaBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
