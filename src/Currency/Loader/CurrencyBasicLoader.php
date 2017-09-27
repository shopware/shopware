<?php declare(strict_types=1);

namespace Shopware\Currency\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Factory\CurrencyBasicFactory;
use Shopware\Currency\Struct\CurrencyBasicCollection;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CurrencyBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CurrencyBasicFactory
     */
    private $factory;

    public function __construct(
        CurrencyBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): CurrencyBasicCollection
    {
        if (empty($uuids)) {
            return new CurrencyBasicCollection();
        }

        $currenciesCollection = $this->read($uuids, $context);

        return $currenciesCollection;
    }

    private function read(array $uuids, TranslationContext $context): CurrencyBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('currency.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CurrencyBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CurrencyBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
