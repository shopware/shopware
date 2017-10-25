<?php declare(strict_types=1);

namespace Shopware\Area\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Area\Factory\AreaBasicFactory;
use Shopware\Area\Struct\AreaBasicCollection;
use Shopware\Area\Struct\AreaBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class AreaBasicReader
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

    public function readBasic(array $uuids, TranslationContext $context): AreaBasicCollection
    {
        if (empty($uuids)) {
            return new AreaBasicCollection();
        }

        $areasCollection = $this->read($uuids, $context);

        return $areasCollection;
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
