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

namespace Shopware\Context\Rule\Container;

use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Match;

/**
 * XorRule returns true, if exactly one child rule is true
 */
class XorRule extends Container
{
    public function match(
        RuleMatchContext $matchContext
    ): Match {
        $matches = 0;
        $messages = [];

        foreach ($this->rules as $rule) {
            $match = $rule->match($matchContext);
            if (!$match->matches()) {
                continue;
            }
            $messages = array_merge($messages, $match->getMessages());
            ++$matches;
        }

        return new Match($matches === 1, $messages);
    }
}
