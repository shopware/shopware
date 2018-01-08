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

namespace Shopware\Storefront\Controller\Widgets;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Entity\Search\Query\TermsQuery;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Storefront\Controller\StorefrontController;

/**
 * @Route(service="shopware.storefront.controller.widgets.index_controller", path="/")
 */
class IndexController extends StorefrontController
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    public function __construct(ShopRepository $shopRepository)
    {
        $this->shopRepository = $shopRepository;
    }

    /**
     * @Route("/widgets/index/shopMenu", name="widgets/shopMenu")
     * @Method({"GET"})
     *
     * @param ShopContext $context
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function shopMenuAction(ShopContext $context)
    {
        return $this->render('@Storefront/widgets/index/shop_menu.html.twig', [
            'shop' => $context->getShop(),
            'currency' => $context->getCurrency(),
            'shops' => $this->loadShops($context),
            'currencies' => $context->getShop()->getCurrencies(),
        ]);
    }

    private function loadShops(ShopContext $context)
    {
        $criteria = new Criteria();

        $ids = array_filter([$context->getShop()->getParentId(), $context->getShop()->getId()]);
        $criteria->addFilter(new TermsQuery('shop.parentId', $ids));
        $criteria->addFilter(new TermQuery('shop.active', 1));

        $shops = $this->shopRepository->search($criteria, $context->getTranslationContext());
        $shops->add($context->getShop());

        return $shops->sortByPosition();
    }
}
