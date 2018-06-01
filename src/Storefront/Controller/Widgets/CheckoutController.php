<?php declare(strict_types=1);
/**
 * Shopware\Core 5
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
 * "Shopware\Core" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Storefront\Controller\Widgets;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\CustomerContext;
use Shopware\Core\Checkout\Cart\StoreFrontCartService;
use Shopware\Storefront\Controller\StorefrontController;

class CheckoutController extends StorefrontController
{
    /**
     * @var StoreFrontCartService
     */
    private $cartService;

    public function __construct(StoreFrontCartService $cartService)
    {
        $this->cartService = $cartService;
    }

    /**
     * @Route("/widgets/checkout/info", name="widgets/checkout/info")
     * @Method({"GET"})
     */
    public function infoAction(CustomerContext $context)
    {
        $cart = $this->cartService->getCalculatedCart($context);

        return $this->render('@Storefront/widgets/checkout/info.html.twig', [
            'cartQuantity' => $cart->getCalculatedLineItems()->filterGoods()->count(),
            'cartAmount' => $cart->getPrice()->getTotalPrice(),
            'sNotesQuantity' => 0,
            'sUserLoggedIn' => false,
        ]);
    }
}
