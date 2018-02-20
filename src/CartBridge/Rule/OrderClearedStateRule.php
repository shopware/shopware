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

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Rule\Match;
use Shopware\CartBridge\Rule\Data\OrderClearedStateRuleData;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class OrderClearedStateRule extends \Shopware\Cart\Rule\Rule
{
    /**
     * @var int[]
     */
    protected $states;

    /**
     * @param int[] $states
     */
    public function __construct(array $states)
    {
        $this->states = $states;
    }

    public function match(
        CalculatedCart $calculatedCart,
        StorefrontContext $context,
        StructCollection $collection
    ): Match {
        if (!$collection->has(OrderClearedStateRuleData::class)) {
            return new Match(
                false,
                ['Order cleared state data not found']
            );
        }

        /** @var OrderClearedStateRuleData $data */
        $data = $collection->get(OrderClearedStateRuleData::class);

        return new Match(
            $data->hasOneState($this->states),
            ['Order states not matched']
        );
    }
}
