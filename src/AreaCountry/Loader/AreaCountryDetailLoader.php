<?php

namespace Shopware\AreaCountry\Loader;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Factory\AreaCountryDetailFactory;
use Shopware\AreaCountry\Struct\AreaCountryDetailCollection;
use Shopware\AreaCountry\Struct\AreaCountryDetailStruct;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearcher;
use Shopware\AreaCountryState\Searcher\AreaCountryStateSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class AreaCountryDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var AreaCountryDetailFactory
     */
    private $factory;

    /**
     * @var AreaCountryStateSearcher
     */
    private $areaCountryStateSearcher;

    public function __construct(
        AreaCountryDetailFactory $factory,
        AreaCountryStateSearcher $areaCountryStateSearcher
    ) {
        $this->factory = $factory;
        $this->areaCountryStateSearcher = $areaCountryStateSearcher;
    }

    public function load(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        if (empty($uuids)) {
            return new AreaCountryDetailCollection();
        }

        $areaCountriesCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('area_country_state.area_country_uuid', $uuids));
        /** @var AreaCountryStateSearchResult $states */
        $states = $this->areaCountryStateSearcher->search($criteria, $context);

        /** @var AreaCountryDetailStruct $areaCountry */
        foreach ($areaCountriesCollection as $areaCountry) {
            $areaCountry->setStates($states->filterByAreaCountryUuid($areaCountry->getUuid()));
        }

        return $areaCountriesCollection;
    }

    private function read(array $uuids, TranslationContext $context): AreaCountryDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('area_country.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AreaCountryDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AreaCountryDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
