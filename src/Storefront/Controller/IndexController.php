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

use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermQuery;
use Shopware\Context\Struct\ShopContext;
use Shopware\Product\Struct\ProductBasicCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class IndexController extends StorefrontController
{
    /**
     * @Route("/", name="homepage", options={"seo"="false"})
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request, ShopContext $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('product.allow_notification', 0));
        $criteria->setLimit(10);

        $repo = $this->get('shopware.product.repository');

        /** @var ProductBasicCollection $a */
        $a = $repo->search($criteria, $context->getTranslationContext());

        foreach ($a as $product) {
        }

        return $this->render('frontend/home/index.html.twig', []);
    }
}
