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

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;

class TaxRuleNotSupportedException extends \Exception
{
    /**
     * @var \Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface
     */
    protected $taxRule;

    public function __construct(TaxRuleInterface $taxRule)
    {
        parent::__construct(
            sprintf('Tax rule %s not supported', get_class($taxRule))
        );
        $this->taxRule = $taxRule;
    }

    public function getTaxRule(): TaxRuleInterface
    {
        return $this->taxRule;
    }
}
