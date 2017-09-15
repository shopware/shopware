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
        $areaCountryStates = $this->read($uuids, $context);

        return $areaCountryStates;
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
