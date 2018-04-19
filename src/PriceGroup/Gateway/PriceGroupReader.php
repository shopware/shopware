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

namespace Shopware\PriceGroup\Gateway;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\PriceGroup\Struct\PriceGroup;
use Shopware\PriceGroup\Struct\PriceGroupCollection;
use Shopware\PriceGroup\Struct\PriceGroupHydrator;

/**
 * @category  Shopware
 *
 * @copyright Copyright (c) shopware AG (http://www.shopware.de)
 */
class PriceGroupReader
{
    use SortArrayByKeysTrait;

    /**
     * @var PriceGroupHydrator
     */
    private $hydrator;

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

    public function __construct(
        Connection $connection,
        FieldHelper $fieldHelper,
        PriceGroupHydrator $priceHydrator
    ) {
        $this->connection = $connection;
        $this->hydrator = $priceHydrator;
        $this->fieldHelper = $fieldHelper;
    }

    public function read(CustomerGroup $customerGroup, TranslationContext $context): PriceGroupCollection
    {
        $query = $this->connection->createQueryBuilder();

        $query->addSelect('priceGroupDiscount.groupID')
            ->addSelect($this->fieldHelper->getPriceGroupDiscountFields())
            ->addSelect($this->fieldHelper->getPriceGroupFields());

        $query->from('s_core_pricegroups_discounts', 'priceGroupDiscount')
            ->innerJoin('priceGroupDiscount', 's_core_pricegroups', 'priceGroup', 'priceGroup.id = priceGroupDiscount.groupID')
            ->andWhere('priceGroupDiscount.customergroupID = :customerGroup')
            ->groupBy('priceGroupDiscount.id')
            ->orderBy('priceGroupDiscount.groupID')
            ->addOrderBy('priceGroupDiscount.discountstart')
            ->setParameter('customerGroup', $customerGroup->getId());

        /** @var $statement \Doctrine\DBAL\Driver\ResultStatement */
        $statement = $query->execute();

        $data = $statement->fetchAll(\PDO::FETCH_GROUP);

        $priceGroups = new PriceGroupCollection();

        foreach ($data as $row) {
            $priceGroup = $this->hydrator->hydrate($row);

            foreach ($priceGroup->getDiscounts() as $discount) {
                $discount->setCustomerGroup($customerGroup);
            }

            $priceGroups->add($priceGroup);
        }

        return $priceGroups;
    }
}
