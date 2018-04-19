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

namespace Shopware\Currency\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Struct\Currency;
use Shopware\Currency\Struct\CurrencyCollection;
use Shopware\Currency\Struct\CurrencyHydrator;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CurrencyReader
{
    use SortArrayByKeysTrait;

    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var CurrencyHydrator
     */
    private $hydrator;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        CurrencyHydrator $hydrator,
        FieldHelper $fieldHelper,
        Connection $connection
    ) {
        $this->hydrator = $hydrator;
        $this->fieldHelper = $fieldHelper;
        $this->connection = $connection;
    }

    public function read(array $ids, TranslationContext $context): CurrencyCollection
    {
        $query = $this->connection->createQueryBuilder();
        $query->addSelect($this->fieldHelper->getCurrencyFields())
            ->from('s_core_currencies', 'currency')
            ->where('currency.id IN (:ids)')
            ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        $currencies = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];
        foreach ($currencies as $row) {
            $currency = $this->hydrator->hydrate($row);
            $result[$currency->getId()] = $currency;
        }

        return new CurrencyCollection(
            $this->sortIndexedArrayByKeys($ids, $result)
        );
    }
}
