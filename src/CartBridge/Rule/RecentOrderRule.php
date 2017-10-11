<?php declare(strict_types=1);
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

namespace Shopware\CartBridge\Rule;

use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Rule\Match;
use Shopware\CartBridge\Rule\Data\RecentOrderRuleData;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class RecentOrderRule extends \Shopware\Cart\Rule\Rule
{
    /**
     * @var int
     */
    protected $days;

    public function __construct(int $days)
    {
        $this->days = $days;
    }

    public function match(
        CalculatedCart $calculatedCart,
        ShopContext $context,
        StructCollection $collection
    ): Match {
        if (!$collection->has(RecentOrderRuleData::class)) {
            return new Match(false, ['No last order data found']);
        }

        /** @var RecentOrderRuleData $data */
        $data = $collection->get(RecentOrderRuleData::class);

        $min = (new \DateTime())->sub(
            new \DateInterval('P' . $this->days . 'D')
        );

        return new Match(
            $min >= $data->getRecentOrderTime(),
            ['Last order not matched']
        );
    }
}
