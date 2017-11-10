<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\AreaCountry\Factory\AreaCountryBasicFactory;
use Shopware\AreaCountry\Struct\AreaCountryBasicCollection;
use Shopware\AreaCountry\Struct\AreaCountryBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class AreaCountryBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var AreaCountryBasicFactory
     */
    private $factory;

    public function __construct(
        AreaCountryBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): AreaCountryBasicCollection
    {
        if (empty($uuids)) {
            return new AreaCountryBasicCollection();
        }

        $areaCountriesCollection = $this->read($uuids, $context);

        return $areaCountriesCollection;
    }

    private function read(array $uuids, TranslationContext $context): AreaCountryBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('area_country.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AreaCountryBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AreaCountryBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
