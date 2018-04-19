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

namespace Shopware\Currency\Struct;

use Shopware\Framework\Struct\Hydrator;

class CurrencyHydrator extends Hydrator
{
    public function hydrate($data): Currency
    {
        $currency = new Currency();
        $currency->setId((int) $data['__currency_id']);
        $currency->setName($data['__currency_name']);
        $currency->setFactor((float) $data['__currency_factor']);
        $currency->setCurrency($data['__currency_currency']);
        $currency->setSymbol($data['__currency_templatechar']);
        $currency->setSymbolPosition($data['__currency_symbol_position']);
        $currency->setPosition((int) $data['__currency_position']);

        return $currency;
    }
}
