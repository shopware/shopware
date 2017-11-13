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

namespace Shopware\CartBridge\CustomerGroup;

use Doctrine\DBAL\Connection;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermQuery;
use Shopware\Cart\Cart\CollectorInterface;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Context\Struct\ShopContext;
use Shopware\CustomerGroupDiscount\Factory\CustomerGroupDiscountBasicFactory;
use Shopware\CustomerGroupDiscount\Repository\CustomerGroupDiscountRepository;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicStruct;
use Shopware\Framework\Struct\StructCollection;

class CustomerGroupDiscountCollector implements CollectorInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CustomerGroupDiscountRepository
     */
    private $customerGroupDiscountRepository;

    /**
     * @var CustomerGroupDiscountBasicFactory
     */
    private $customerGroupDiscountBasicFactory;

    /**
     * @param Connection $connection
     */
    public function __construct(
        Connection $connection,
        CustomerGroupDiscountRepository $customerGroupDiscountRepository,
        CustomerGroupDiscountBasicFactory $customerGroupDiscountBasicFactory
    )
    {
        $this->connection = $connection;
        $this->customerGroupDiscountRepository = $customerGroupDiscountRepository;
        $this->customerGroupDiscountBasicFactory = $customerGroupDiscountBasicFactory;
    }

    public function prepare(
        StructCollection $fetchDefinition,
        CartContainer $cartContainer,
        ShopContext $context
    ): void
    {
    }

    public function fetch(
        StructCollection $dataCollection,
        StructCollection $fetchCollection,
        ShopContext $context
    ): void
    {

        $criteria = new Criteria();
        $criteria->addFilter(
            new TermQuery(
                'customer_group_discount.customer_group_uuid',
                $context->getCurrentCustomerGroup()->getUuid()
            )
        );
        $discounts = $this->customerGroupDiscountRepository->search($criteria, $context->getTranslationContext());

        $discounts->sort(function (CustomerGroupDiscountBasicStruct $a, CustomerGroupDiscountBasicStruct $b) {
            if ($a->getMinimumCartAmount() !== $b->getMinimumCartAmount()) {
                return -1;
            }
            return $a->getMinimumCartAmount() > $b->getMinimumCartAmount();
        });

        $dataCollection->add(new CustomerGroupDiscountBasicCollection($discounts->getElements()),
            CustomerGroupDiscountProcessor::class
        );
    }
}
