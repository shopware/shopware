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

namespace Shopware\ProductPrice\Struct;

use Shopware\Framework\Struct\Struct;

class ProductPriceBasicStruct extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string
     */
    protected $pricegroup;

    /**
     * @var int
     */
    protected $from;

    /**
     * @var string
     */
    protected $to;

    /**
     * @var int
     */
    protected $productId;

    /**
     * @var string
     */
    protected $productUuid;

    /**
     * @var int
     */
    protected $productDetailId;

    /**
     * @var string
     */
    protected $productDetailUuid;

    /**
     * @var float
     */
    protected $price;

    /**
     * @var float|null
     */
    protected $pseudoprice;

    /**
     * @var float|null
     */
    protected $baseprice;

    /**
     * @var float|null
     */
    protected $percent;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getPricegroup(): string
    {
        return $this->pricegroup;
    }

    public function setPricegroup(string $pricegroup): void
    {
        $this->pricegroup = $pricegroup;
    }

    public function getFrom(): int
    {
        return $this->from;
    }

    public function setFrom(int $from): void
    {
        $this->from = $from;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function setTo(string $to): void
    {
        $this->to = $to;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function setProductId(int $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductUuid(): string
    {
        return $this->productUuid;
    }

    public function setProductUuid(string $productUuid): void
    {
        $this->productUuid = $productUuid;
    }

    public function getProductDetailId(): int
    {
        return $this->productDetailId;
    }

    public function setProductDetailId(int $productDetailId): void
    {
        $this->productDetailId = $productDetailId;
    }

    public function getProductDetailUuid(): string
    {
        return $this->productDetailUuid;
    }

    public function setProductDetailUuid(string $productDetailUuid): void
    {
        $this->productDetailUuid = $productDetailUuid;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function getPseudoprice(): ?float
    {
        return $this->pseudoprice;
    }

    public function setPseudoprice(?float $pseudoprice): void
    {
        $this->pseudoprice = $pseudoprice;
    }

    public function getBaseprice(): ?float
    {
        return $this->baseprice;
    }

    public function setBaseprice(?float $baseprice): void
    {
        $this->baseprice = $baseprice;
    }

    public function getPercent(): ?float
    {
        return $this->percent;
    }

    public function setPercent(?float $percent): void
    {
        $this->percent = $percent;
    }
}
