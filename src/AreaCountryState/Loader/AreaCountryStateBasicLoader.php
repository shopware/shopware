<?php declare(strict_types=1);

namespace Shopware\AreaCountryState\Loader;

use Doctrine\DBAL\Connection;
use Shopware\AreaCountryState\Factory\AreaCountryStateBasicFactory;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicCollection;
use Shopware\AreaCountryState\Struct\AreaCountryStateBasicStruct;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class AreaCountryStateBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var AreaCountryStateBasicFactory
     */
    private $factory;

    public function __construct(
        AreaCountryStateBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): AreaCountryStateBasicCollection
    {
        if (empty($uuids)) {
            return new AreaCountryStateBasicCollection();
        }

        $areaCountryStatesCollection = $this->read($uuids, $context);

        return $areaCountryStatesCollection;
    }

    private function read(array $uuids, TranslationContext $context): AreaCountryStateBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('area_country_state.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new AreaCountryStateBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new AreaCountryStateBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
