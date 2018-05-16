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

namespace Shopware\Checkout\Rule\Specification\Context;

use Shopware\Checkout\Rule\Specification\Scope\RuleScope;
use Shopware\Checkout\Rule\Specification\Match;
use Shopware\Checkout\Rule\Specification\Rule;

class ShippingZipCodeRule extends Rule
{
    /**
     * @var string[]
     */
    protected $zipCodes;

    /**
     * @param string[] $zipCodes
     */
    public function __construct(array $zipCodes)
    {
        $this->zipCodes = $zipCodes;
    }

    public function match(
        RuleScope $scope
    ): Match {
        if (!$location = $scope->getContext()->getShippingLocation()->getAddress()) {
            return new Match(
                false,
                ['Shipping location has no address']
            );
        }

        return new Match(
            in_array($location->getZipcode(), $this->zipCodes, true),
            ['Shipping zip code not matched']
        );
    }
}
