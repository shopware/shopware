<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

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
        $currencies = $this->read($uuids, $context);

        return $currencies;
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
