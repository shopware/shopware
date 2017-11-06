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

namespace Shopware\Cart\LineItem;

use Shopware\Cart\Price\Struct\Price;
use Shopware\CartBridge\View\ViewLineItemInterface;
use Shopware\Framework\Struct\Struct;
use Shopware\Media\Struct\MediaBasicStruct;

class Discount extends Struct implements CalculatedLineItemInterface, ViewLineItemInterface
{
    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var Price
     */
    protected $price;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $type = 'discount';

    /**
     * @param string $identifier
     * @param Price  $price
     * @param string $label
     */
    public function __construct($identifier, Price $price, string $label)
    {
        $this->identifier = $identifier;
        $this->price = $price;
        $this->label = $label;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getPrice(): Price
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return 1;
    }

    public function getLineItem(): ? LineItemInterface
    {
        return null;
    }

    public function getCalculatedLineItem(): CalculatedLineItemInterface
    {
        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCover(): ? MediaBasicStruct
    {
        return null;
    }

    public function getType(): string
    {
        return $this->type;
    }
}
