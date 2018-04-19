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

namespace Shopware\CustomerGroup\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\CustomerGroup\Struct\CustomerGroupCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupHydrator;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class CustomerGroupReader
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerGroupHydrator
     */
    private $customerGroupHydrator;

    /**
     * The FieldHelper class is used for the
     * different table column definitions.
     *
     * This class helps to select each time all required
     * table data for the store front.
     *
     * Additionally the field helper reduce the work, to
     * select in a second step the different required
     * attribute tables for a parent table.
     *
     * @var FieldHelper
     */
    private $fieldHelper;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection                                           $connection
     * @param FieldHelper                                          $fieldHelper
     * @param \Shopware\CustomerGroup\Struct\CustomerGroupHydrator $customerGroupHydrator
     */
    public function __construct(
        Connection $connection,
        FieldHelper $fieldHelper,
        CustomerGroupHydrator $customerGroupHydrator
    ) {
        $this->customerGroupHydrator = $customerGroupHydrator;
        $this->connection = $connection;
        $this->fieldHelper = $fieldHelper;
    }

    public function read(array $keys, TranslationContext $context): CustomerGroupCollection
    {
        $query = $this->connection->createQueryBuilder();
        $query->select($this->fieldHelper->getCustomerGroupFields());

        $query->from('s_core_customergroups', 'customerGroup')
            ->leftJoin('customerGroup', 's_core_customergroups_attributes', 'customerGroupAttribute', 'customerGroupAttribute.customerGroupID = customerGroup.id')
            ->where('customerGroup.groupkey IN (:keys)')
            ->setParameter('keys', $keys, Connection::PARAM_STR_ARRAY);

        /** @var $statement \Doctrine\DBAL\Driver\ResultStatement */
        $statement = $query->execute();

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $customerGroups = [];
        foreach ($data as $group) {
            $key = $group['__customerGroup_groupkey'];
            $customerGroups[$key] = $this->customerGroupHydrator->hydrate($group);
        }

        return new CustomerGroupCollection(
            $this->sortIndexedArrayByKeys($keys, $customerGroups)
        );
    }
}
