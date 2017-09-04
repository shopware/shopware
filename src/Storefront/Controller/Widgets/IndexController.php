<?php
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

namespace Shopware\Storefront\Controller\Widgets;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\ShopContext;
use Shopware\Search\Condition\ActiveCondition;
use Shopware\Search\Condition\ParentCondition;
use Shopware\Search\Condition\ShopCondition;
use Shopware\Search\Criteria;
use Shopware\Storefront\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class IndexController extends Controller
{
    /**
     * @Route("/widgets/index/shopMenu", name="widgets/shopMenu")
     * @Method({"GET"})
     *
     * @param ShopContext $context
     * @param Request     $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function shopMenuAction(ShopContext $context, Request $request)
    {
        return $this->render('@Shopware/widgets/index/shop_menu.html.twig', [
            'shop' => $context->getShop(),
            'currency' => $context->getCurrency(),
            'shops' => $this->loadShops($context),
            'currencies' => $this->loadCurrencies($context),
        ]);
    }

    private function loadShops(ShopContext $context)
    {
        $criteria = new Criteria();

        $criteria->addCondition(new ParentCondition([$context->getShop()->getMainId()]));
        $criteria->addCondition(new ActiveCondition(true));

        $repo = $this->get('shopware.shop.repository');

        $shops = $repo->search($criteria, $context->getTranslationContext());

        $ids = array_merge([$context->getShop()->getMainId()], $shops->getIds());
        $shops = $repo->read($ids, $context->getTranslationContext());

        return $shops->sortByPosition();
    }

    private function loadCurrencies(ShopContext $context)
    {
        $criteria = new Criteria();
        $criteria->addCondition(new ShopCondition([$context->getShop()->getMainId()]));

        $repo = $this->get('shopware.currency.gateway.currency_repository');
        $currencies = $repo->search($criteria, $context->getTranslationContext());
        $currencies->sortByPosition();

        return $currencies;
    }
}
