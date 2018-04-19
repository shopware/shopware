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
use Shopware\Media\Struct\Media;
use Shopware\Product\Struct\Product;

class ViewProduct extends Product implements ViewLineItemInterface
{
    /**
     * @var CalculatedProduct
     */
    protected $product;

    /**
     * @var string
     */
    protected $type = 'product';

    public function __construct(string $uuid, string $variantUuid, string $number, CalculatedProduct $product)
    {
        $this->product = $product;
    }

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
        Product $simpleProduct,
        CalculatedProduct $calculatedProduct
    ): ViewProduct {
        $product = new self(
            $simpleProduct->getUuid(),
            $simpleProduct->getVariantUuid(),
            $simpleProduct->getNumber(),
            $calculatedProduct
        );
        foreach ($simpleProduct as $key => $value) {
            $product->$key = $value;
        }

        return $product;
    }

    public function getCover(): ? Media
    {
        return null;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        $data['label'] = $this->getLabel();

        return $data;
    }
}
