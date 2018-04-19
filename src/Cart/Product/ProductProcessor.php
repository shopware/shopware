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

namespace Shopware\Cart\Product;

use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\Cart\ProcessorCart;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class ProductProcessor implements CartProcessorInterface
{
    const TYPE_PRODUCT = 'product';

    /**
     * @var ProductCalculator
     */
    private $calculator;

    /**
     * @param ProductCalculator $calculator
     */
    public function __construct(ProductCalculator $calculator)
    {
        $this->calculator = $calculator;
    }

    public function process(
        CartContainer $cartContainer,
        ProcessorCart $processorCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void {
        $collection = $cartContainer->getLineItems()->filterType(self::TYPE_PRODUCT);

        if ($collection->count() === 0) {
            return;
        }

        $products = $this->calculator->calculate($collection, $context, $dataCollection);

        $cartContainer->getErrors()->fill($products->getErrors());

        $processorCart->getCalculatedLineItems()->fill(
            $products->getIterator()->getArrayCopy()
        );
    }
}
