<?php
declare(strict_types=1);
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

namespace Shopware\CountryState\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CountryState\Struct\CountryState;
use Shopware\CountryState\Struct\CountryStateCollection;
use Shopware\CountryState\Struct\CountryStateHydrator;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CountryStateReader
{
    use SortArrayByKeysTrait;

    /**
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var CountryStateHydrator
     */
    private $hydrator;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param FieldHelper          $fieldHelper
     * @param CountryStateHydrator $hydrator
     * @param Connection           $connection
     */
    public function __construct(
        FieldHelper $fieldHelper,
        CountryStateHydrator $hydrator,
        Connection $connection
    ) {
        $this->fieldHelper = $fieldHelper;
        $this->hydrator = $hydrator;
        $this->connection = $connection;
    }

    public function read(array $ids, TranslationContext $context): CountryStateCollection
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->fieldHelper->getCountryStateFields());

        $query->from('s_core_countries_states', 'countryState')
            ->leftJoin('countryState', 's_core_countries_states_attributes', 'countryStateAttribute', 'countryStateAttribute.stateID = countryState.id');

        $this->fieldHelper->addCountryStateTranslation($query, $context);

        $query->where('countryState.id IN (:ids)')
            ->setParameter('ids', $ids, Connection::PARAM_INT_ARRAY);

        /** @var $statement \Doctrine\DBAL\Driver\ResultStatement */
        $statement = $query->execute();

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $states = [];
        foreach ($data as $row) {
            /** @var CountryState $state */
            $state = $this->hydrator->hydrate($row);
            $states[$state->getId()] = $state;
        }

        return new CountryStateCollection(
            $this->sortIndexedArrayByKeys($ids, $states)
        );
    }
}
