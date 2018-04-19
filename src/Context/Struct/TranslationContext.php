<?php
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

namespace Shopware\Context\Struct;

use Shopware\Shop\Struct\Shop;

class TranslationContext
{
    /**
     * @var int
     */
    private $shopId;

    /**
     * @var int|null
     */
    private $fallbackId;

    /**
     * @var bool
     */
    private $isDefaultShop;

    /**
     * @param int      $shopId
     * @param bool     $isDefaultShop
     * @param int|null $fallbackId
     */
    public function __construct(int $shopId, bool $isDefaultShop, ?int $fallbackId)
    {
        $this->shopId = $shopId;
        $this->fallbackId = $fallbackId;
        $this->isDefaultShop = $isDefaultShop;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getFallbackId(): ? int
    {
        return $this->fallbackId;
    }

    public function isDefaultShop(): bool
    {
        return $this->isDefaultShop;
    }

    public static function createFromShop(Shop $shop): TranslationContext
    {
        return new self(
            $shop->getId(),
            $shop->isDefault(),
            $shop->getMainId()
        );
    }
}
