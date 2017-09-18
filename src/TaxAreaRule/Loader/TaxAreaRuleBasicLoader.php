<?php

namespace Shopware\TaxAreaRule\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\TaxAreaRule\Factory\TaxAreaRuleBasicFactory;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicCollection;
use Shopware\TaxAreaRule\Struct\TaxAreaRuleBasicStruct;

class TaxAreaRuleBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var TaxAreaRuleBasicFactory
     */
    private $factory;

    public function __construct(
        TaxAreaRuleBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): TaxAreaRuleBasicCollection
    {
        $taxAreaRules = $this->read($uuids, $context);

        return $taxAreaRules;
    }

    private function read(array $uuids, TranslationContext $context): TaxAreaRuleBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('tax_area_rule.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new TaxAreaRuleBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new TaxAreaRuleBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
