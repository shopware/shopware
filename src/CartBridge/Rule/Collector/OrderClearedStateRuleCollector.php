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

namespace Shopware\CartBridge\Rule\Collector;

use Doctrine\DBAL\Connection;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CollectorInterface;
use Shopware\Cart\Rule\RuleCollection;
use Shopware\Cart\Rule\Validatable;
use Shopware\CartBridge\Rule\Data\OrderClearedStateRuleData;
use Shopware\CartBridge\Rule\OrderClearedStateRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\IndexedCollection;

class OrderClearedStateRuleCollector implements CollectorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function prepare(
        IndexedCollection $fetchDefinition,
        CartContainer $cartContainer,
        ShopContext $context
    ): void {
    }

    public function fetch(
        IndexedCollection $dataCollection,
        IndexedCollection $fetchCollection,
        ShopContext $context
    ): void {
        $rules = $dataCollection->filterInstance(Validatable::class);

        $rules = $rules->map(function (Validatable $validatable) {
            return $validatable->getRule();
        });

        $rules = new RuleCollection($rules);
        if (!$rules->has(OrderClearedStateRule::class)) {
            return;
        }
        if (!$customer = $context->getCustomer()) {
            return;
        }

        $query = $this->connection->createQueryBuilder();
        $query->select('DISTINCT cleared');
        $query->from('s_order');
        $query->where('userID = :userId');
        $query->setParameter('userId', $customer->getUuid());

        $states = $query->execute()->fetchAll(\PDO::FETCH_COLUMN);

        $dataCollection->add(
            new OrderClearedStateRuleData($states),
            OrderClearedStateRuleData::class
        );
    }
}
