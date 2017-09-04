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

namespace Shopware\Currency\Reader;

use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Framework\Struct\Hydrator;

class CurrencyBasicHydrator extends Hydrator
{
    public function __construct()
    {
    }

    public function hydrate(array $data): CurrencyBasicStruct
    {
        $currency = new CurrencyBasicStruct();

        $currency->setId((int)$data['__currency_id']);
        $currency->setUuid((string)$data['__currency_uuid']);
        $currency->setCurrency((string)$data['__currency_currency']);
        $currency->setName((string)$data['__currency_name']);
        $currency->setStandard((bool)$data['__currency_standard']);
        $currency->setFactor((float)$data['__currency_factor']);
        $currency->setTemplateChar((string)$data['__currency_template_char']);
        $currency->setSymbolPosition((int)$data['__currency_symbol_position']);
        $currency->setPosition((int)$data['__currency_position']);

        return $currency;
    }
}
