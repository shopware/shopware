<?php declare(strict_types=1);

namespace Shopware\Area\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Area\Factory\AreaDetailFactory;
use Shopware\Area\Struct\AreaDetailCollection;
use Shopware\Area\Struct\AreaDetailStruct;
use Shopware\AreaCountry\Reader\AreaCountryDetailReader;
use Shopware\AreaCountry\Searcher\AreaCountrySearcher;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class AreaDetailReader
{
    use SortArrayByKeysTrait;

    /**
     * @var AreaDetailFactory
     */
    private $factory;

    /**
     * @var AreaCountrySearcher
     */
    private $areaCountrySearcher;

    /**
     * @var AreaCountryDetailReader
     */
    private $areaCountryDetailReader;

    public function __construct(
        AreaDetailFactory $factory,
        AreaCountrySearcher $areaCountrySearcher,
        AreaCountryDetailReader $areaCountryDetailReader
    ) {
        $this->factory = $factory;
        $this->areaCountrySearcher = $areaCountrySearcher;
        $this->areaCountryDetailReader = $areaCountryDetailReader;
    }

    public function readDetail(array $uuids, TranslationContext $context): AreaDetailCollection
    {
        if (empty($uuids)) {
            return new AreaDetailCollection();
        }

        $areasCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('area_country.areaUuid', $uuids));
        $countriesUuids = $this->areaCountrySearcher->searchUuids($criteria, $context);
        $countries = $this->areaCountryDetailReader->readDetail($countriesUuids->getUuids(), $context);

        /** @var AreaDetailStruct $area */
        foreach ($areasCollection as $area) {
            $area->setCountries($countries->filterByAreaUuid($area->getUuid()));
        }

        return $areasCollection;
    }

    private function read(array $uuids, TranslationContext $context): AreaDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('area.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AreaDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AreaDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
