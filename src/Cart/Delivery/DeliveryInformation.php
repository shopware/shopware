<?php
declare(strict_types=1);
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

namespace Shopware\Cart\Delivery;

use Shopware\Framework\Struct\Struct;

class DeliveryInformation extends Struct
{
    /**
     * @var int
     */
    protected $stock;

    /**
     * @var float
     */
    protected $height;

    /**
     * @var float
     */
    protected $width;

    /**
     * @var float
     */
    protected $length;

    /**
     * @var float
     */
    protected $weight;

    /**
     * @var DeliveryDate
     */
    protected $inStockDeliveryDate;

    /**
     * @var DeliveryDate
     */
    protected $outOfStockDeliveryDate;

    public function __construct(
        int $stock,
        float $height,
        float $width,
        float $length,
        float $weight,
        DeliveryDate $inStockDeliveryDate,
        DeliveryDate $outOfStockDeliveryDate
    ) {
        $this->stock = $stock;
        $this->height = $height;
        $this->width = $width;
        $this->length = $length;
        $this->weight = $weight;
        $this->inStockDeliveryDate = $inStockDeliveryDate;
        $this->outOfStockDeliveryDate = $outOfStockDeliveryDate;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    public function getWidth(): float
    {
        return $this->width;
    }

    public function getLength(): float
    {
        return $this->length;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getInStockDeliveryDate(): DeliveryDate
    {
        return $this->inStockDeliveryDate;
    }

    public function getOutOfStockDeliveryDate(): DeliveryDate
    {
        return $this->outOfStockDeliveryDate;
    }
}
