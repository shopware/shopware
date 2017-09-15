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

use Shopware\Shop\Struct\ShopBasicCollection;

class CurrencyDetailStruct extends CurrencyBasicStruct
{
    /**
     * @var string[]
     */
    protected $shopUuids = [];

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    public function __construct()
    {
        $this->shops = new ShopBasicCollection();
    }

    public function getShopUuids(): array
    {
        return $this->shopUuids;
    }

    public function setShopUuids(array $shopUuids): void
    {
        $this->shopUuids = $shopUuids;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
    }
}
