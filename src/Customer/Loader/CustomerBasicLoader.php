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

namespace Shopware\Customer\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Factory\CustomerBasicFactory;
use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CustomerBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerBasicFactory
     */
    private $factory;

    public function __construct(
        CustomerBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): CustomerBasicCollection
    {
        $customers = $this->read($uuids, $context);

        return $customers;
    }

    private function read(array $uuids, TranslationContext $context): CustomerBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
