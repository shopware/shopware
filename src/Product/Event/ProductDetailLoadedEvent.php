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

namespace Shopware\Product\Event;

use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Struct\ProductDetailCollection;
use Shopware\ProductDetail\Event\ProductDetailBasicLoadedEvent;

class ProductDetailLoadedEvent extends NestedEvent
{
    const NAME = 'product.detail.loaded';

    /**
     * @var ProductDetailCollection
     */
    protected $products;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductDetailCollection $products, TranslationContext $context)
    {
        $this->products = $products;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProducts(): ProductDetailCollection
    {
        return $this->products;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new ProductBasicLoadedEvent($this->products, $this->context),
            new ProductDetailBasicLoadedEvent($this->products->getDetails(), $this->context),
            new CategoryBasicLoadedEvent($this->products->getCategories(), $this->context),
        ]);
    }
}
