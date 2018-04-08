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
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Navigation\NavigationService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route(service="Shopware\Storefront\Controller\Widgets\NavigationController", path="/")
 */
class NavigationController extends StorefrontController
{
    /**
     * @Route("/widgets/navigation/navigation", name="widgets/navigation/main")
     * @Method({"GET"})
     *
     * @param StorefrontContext $context
     * @return null|Response
     */
    public function navigationAction(StorefrontContext $context): ?Response
    {
        $request = $this->get('request_stack')->getMasterRequest();

        if (!$request) {
            return null;
        }

        $navigationId = $request->attributes->get('active_category_id');

        $navigation = $this->get(NavigationService::class)->load($navigationId, $context);

        return $this->render('@Storefront/widgets/navigation/navigation.html.twig', [
            'navigation' => $navigation
        ]);
    }

    /**
     * @Route("/widgets/navigation/sidebar", name="widgets/navigation/sidebar")
     * @Method({"GET"})
     *
     * @param StorefrontContext $context
     * @return null|Response
     */
    public function sidebarAction(StorefrontContext $context): ?Response
    {
        $request = $this->get('request_stack')->getMasterRequest();

        if (!$request) {
            return null;
        }

        $navigationId = $request->attributes->get('active_category_id');

        $navigation = $this->get(NavigationService::class)->load($navigationId, $context);

        return $this->render('@Storefront/widgets/navigation/sidebar.html.twig', [
            'navigation' => $navigation
        ]);
    }
}
