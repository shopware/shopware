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

namespace Shopware\Context\Rule\CalculatedCart;

use Shopware\Checkout\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Context\MatchContext\CartRuleMatchContext;
use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Match;
use Shopware\Context\Rule\Rule;

class LineItemsInCartRule extends Rule
{
    /**
     * @var string[]
     */
    private $identifiers;

    /**
     * @param string[] $identifiers
     */
    public function __construct(array $identifiers)
    {
        $this->identifiers = $identifiers;
    }

    public function match(
        RuleMatchContext $matchContext
    ): Match {
        if (!$matchContext instanceof CartRuleMatchContext) {
            return new Match(
                false,
                ['Invalid Match Context. CartRuleMatchContext expected']
            );
        }

        $elements = $matchContext->getCalculatedCart()->getCalculatedLineItems()->getFlatElements();
        $identifiers = array_map(function (CalculatedLineItemInterface $element) {
            return $element->getIdentifier();
        }, $elements);

        return new Match(
            !empty(array_intersect($identifiers, $this->identifiers)),
            ['Line items not in cart']
        );
    }

    public function getIdentifiers(): array
    {
        return $this->identifiers;
    }
}
