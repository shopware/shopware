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
use Shopware\CartBridge\Service\StoreFrontCartService;
use Shopware\Storefront\Controller\StorefrontController;

/**
 * @Route(service="shopware.storefront.controller.widgets.checkout_controller")
 */
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
    public function infoAction()
    {
        return $this->render('@Storefront/widgets/checkout/info.html.twig', [
            'cartQuantity' => $this->cartService->getCartContainer()->getLineItems()->count(),
            'cartAmount' => $this->cartService->getCart()->getCalculatedCart()->getPrice()->getTotalPrice(),
            'sNotesQuantity' => 0,
            'sUserLoggedIn' => false,
        ]);
    }
}
