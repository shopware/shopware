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

namespace Shopware\Context\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Framework\Struct\Struct;

class TranslationContext extends Struct
{
    /**
     * @var string|null
     */
    protected $fallbackId;

    /**
     * @var bool
     */
    protected $isDefaultShop;

    /**
     * @var string
     */
    protected $shopId;

    public function __construct(string $shopId, bool $isDefaultShop, ?string $fallbackId)
    {
        $this->fallbackId = $fallbackId;
        $this->isDefaultShop = $isDefaultShop;
        $this->shopId = $shopId;
    }

    public function getFallbackId(): ? string
    {
        return $this->fallbackId;
    }

    public function isDefaultShop(): bool
    {
        return $this->isDefaultShop;
    }

    public static function createDefaultContext(): self
    {
        return new self('ffa32a50-e2d0-4cf3-8389-a53f8d6cd594', true, null);
    }

    public static function createFromShop(ShopBasicStruct $shop): self
    {
        return new self(
            $shop->getId(),
            $shop->getIsDefault(),
            $shop->getParentId()
        );
    }

    public function hasFallback(): bool
    {
        return !$this->isDefaultShop() && $this->getFallbackId() !== $this->getShopId();
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }
}
