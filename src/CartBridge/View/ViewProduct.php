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

namespace Shopware\CartBridge\View;

use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\Product\CalculatedProduct;
use Shopware\Media\Struct\MediaBasicStruct;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\ProductDetail\Struct\ProductDetailBasicStruct;

class ViewProduct extends ProductBasicStruct implements ViewLineItemInterface
{
    /**
     * @var CalculatedProduct
     */
    protected $product;

    /**
     * @var ProductDetailBasicStruct
     */
    protected $variant;

    /**
     * @var string
     */
    protected $type = 'product';

    /**
     * {@inheritdoc}
     */
    public function getCalculatedLineItem(): CalculatedLineItemInterface
    {
        return $this->product;
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel(): string
    {
        return $this->name;
    }

    public static function createFromProducts(
        ProductBasicStruct $simpleProduct,
        ProductDetailBasicStruct $variant,
        CalculatedProduct $calculatedProduct
    ): ViewProduct {

        $product = new self();

        foreach ($simpleProduct as $key => $value) {
            $product->$key = $value;
        }

        $product->variant = $variant;
        $product->product = $calculatedProduct;

        return $product;
    }

    public function getCover(): ? MediaBasicStruct
    {
        return null;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['label'] = $this->getLabel();

        return $data;
    }

    /**
     * @return ProductDetailBasicStruct
     */
    public function getVariant(): ProductDetailBasicStruct
    {
        return $this->variant;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

}
