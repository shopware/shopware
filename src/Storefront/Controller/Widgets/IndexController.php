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
use Shopware\Api\Currency\Collection\CurrencyBasicCollection;
use Shopware\Api\Currency\Repository\CurrencyRepository;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Controller\StorefrontController;

/**
 * @Route(service="Shopware\Storefront\Controller\Widgets\IndexController", path="/")
 */
class IndexController extends StorefrontController
{
    /**
     * @var ShopRepository
     */
    private $shopRepository;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    public function __construct(ShopRepository $shopRepository, CurrencyRepository $currencyRepository)
    {
        $this->shopRepository = $shopRepository;
        $this->currencyRepository = $currencyRepository;
    }

    /**
     * @Route("/widgets/index/shopMenu", name="widgets/shopMenu")
     * @Method({"GET"})
     *
     * @param StorefrontContext $context
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function shopMenuAction(StorefrontContext $context)
    {
        return $this->render('@Storefront/widgets/index/shop_menu.html.twig', [
            'shop' => $context->getShop(),
            'currency' => $context->getCurrency(),
            'shops' => $this->loadShops($context),
            'currencies' => $this->getCurrencies($context),
        ]);
    }

    private function loadShops(StorefrontContext $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('shop.active', 1));

        $shops = $this->shopRepository->search($criteria, $context->getShopContext());
        $shops->add($context->getShop());

        return $shops->sortByPosition();
    }

    private function getCurrencies(StorefrontContext $context): CurrencyBasicCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('currency.shops.id', $context->getShop()->getId()));

        return $this->currencyRepository->search($criteria, $context->getShopContext());
    }
}
