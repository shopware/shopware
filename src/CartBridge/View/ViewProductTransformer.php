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

use Shopware\Bundle\StoreFrontBundle\Media\MediaServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Product\ProductGateway;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Product\CalculatedProduct;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Gateway\ProductRepository;

class ViewProductTransformer implements ViewLineItemTransformerInterface
{
    /**
     * @var MediaServiceInterface
     */
    private $mediaService;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    public function __construct(ProductRepository $productRepository)
    {
        $this->productRepository = $productRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(
        CalculatedCart $calculatedCart,
        ViewCart $templateCart,
        ShopContext $context
    ): void {
        $collection = $calculatedCart->getCalculatedLineItems()->filterInstance(CalculatedProduct::class);

        if ($collection->count() === 0) {
            return;
        }

        $listProducts = $this->productRepository->read(
            $collection->getIdentifiers(),
            $context->getTranslationContext(),
            ProductRepository::FETCH_MINIMAL
        );

//        $covers = $this->mediaService->getVariantCovers($listProducts, $context);

        foreach ($listProducts as $listProduct) {
            /** @var CalculatedProduct $calculated */
            $calculated = $collection->get($listProduct->getNumber());

//            if (isset($covers[$listProduct->getNumber()])) {
//                $listProduct->setCover($covers[$listProduct->getNumber()]);
//            }

            $template = ViewProduct::createFromProducts($listProduct, $calculated);

            $templateCart->getViewLineItems()->add($template);
        }
    }
}
