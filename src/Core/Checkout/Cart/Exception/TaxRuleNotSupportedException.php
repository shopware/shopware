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

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleInterface;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class TaxRuleNotSupportedException extends ShopwareHttpException
{
    /**
     * @var TaxRuleInterface
     */
    protected $taxRule;

    protected $code = 'TAX-RULE-NOT-SUPPORTED';

    public function __construct(TaxRuleInterface $taxRule, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Tax rule %s not supported', get_class($taxRule));
        parent::__construct($message, $code, $previous);
        $this->taxRule = $taxRule;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_IMPLEMENTED;
    }

    public function getTaxRule(): TaxRuleInterface
    {
        return $this->taxRule;
    }
}
