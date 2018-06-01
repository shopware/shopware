<?php declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Core\Checkout\Rule\Specification\Context;

use Shopware\Core\Checkout\Rule\Specification\Match;
use Shopware\Core\Checkout\Rule\Specification\Rule;
use Shopware\Core\Checkout\Rule\Specification\Scope\RuleScope;

class CustomerNumberRule extends Rule
{
    /**
     * @var string[]
     */
    protected $numbers;

    /**
     * @param string[] $numbers
     */
    public function __construct(array $numbers)
    {
        $this->numbers = array_map('strtolower', $numbers);
    }

    public function match(
        RuleScope $scope
    ): Match {
        if (!$customer = $scope->getContext()->getCustomer()) {
            return new Match(false, ['Not logged in customer']);
        }

        return new Match(
            in_array(strtolower($customer->getNumber()), $this->numbers, true),
            ['Customer number not match']
        );
    }
}
