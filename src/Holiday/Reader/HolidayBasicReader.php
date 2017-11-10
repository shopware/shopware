<?php declare(strict_types=1);

namespace Shopware\Holiday\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Holiday\Factory\HolidayBasicFactory;
use Shopware\Holiday\Struct\HolidayBasicCollection;
use Shopware\Holiday\Struct\HolidayBasicStruct;

class HolidayBasicReader implements BasicReaderInterface
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

    public function readBasic(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        if (empty($uuids)) {
            return new HolidayBasicCollection();
        }

        $holidaysCollection = $this->read($uuids, $context);

        return $holidaysCollection;
    }

    private function read(array $uuids, TranslationContext $context): HolidayBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('holiday.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

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
