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

namespace Shopware\Storefront\Controller;

use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Struct\ProductDetailStruct;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DetailController extends StorefrontController
{
    /**
     * @Route("/detail/{uuid}", name="detail_page", options={"seo"="true"})
     */
    public function indexAction(string $uuid, ShopContext $context, Request $request)
    {
        $loader = $this->get('shopware.storefront.page.detail.detail_page_loader');

        /** @var ProductDetailStruct $productDetail */
        $productDetail = $loader->load($uuid, $context);

        return $this->render('@Storefront/frontend/detail/index.html.twig', [
            'product' => $productDetail,
        ]);
    }
}
