<?php declare(strict_types=1);

namespace Shopware\ListingSorting\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ListingSorting\Factory\ListingSortingBasicFactory;
use Shopware\ListingSorting\Struct\ListingSortingBasicCollection;
use Shopware\ListingSorting\Struct\ListingSortingBasicStruct;

class ListingSortingBasicReader implements BasicReaderInterface
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

    public function readBasic(array $uuids, TranslationContext $context): ListingSortingBasicCollection
    {
        if (empty($uuids)) {
            return new ListingSortingBasicCollection();
        }

        $listingSortingsCollection = $this->read($uuids, $context);

        return $listingSortingsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ListingSortingBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('listing_sorting.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

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
