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

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Content\Rule\Exception\UnsupportedOperatorException;

class SalesChannelRule extends Rule
{
    /**
     * @var int[]
     */
    protected $salesChannelIds;

    /**
     * @var string
     */
    protected $operator;

    public function __construct(array $salesChannelIds, string $operator)
    {
        $this->salesChannelIds = $salesChannelIds;
        $this->operator = $operator;
    }

    public function match(RuleScope $scope): Match
    {
        $context = $scope->getContext();

        switch ($this->operator) {
            case self::OPERATOR_EQ:

                return new Match(
                    \in_array($context->getSourceContext()->getSalesChannelId(), $this->salesChannelIds, true),
                    ['SalesChannel not matched']
                );

            case self::OPERATOR_NEQ:

                return new Match(
                    !\in_array($context->getSourceContext()->getSalesChannelId(), $this->salesChannelIds, true),
                    ['SalesChannel not matched']
                );

            default:
                throw new UnsupportedOperatorException($this->operator, __CLASS__);
        }
    }
}
