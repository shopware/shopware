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

use Shopware\Storefront\Navigation\Navigation;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Shopware\Storefront\Navigation\NavigationService;
use Shopware\Storefront\Context\StorefrontContextService;
use Shopware\Storefront\Twig\TemplateFinder;

abstract class StorefrontController extends Controller
{
    /**
     * {@inheritdoc}
     */
    protected function renderStorefront($view, array $parameters = [], Response $response = null): Response
    {
        $view = $this->resolveView($view);
        $parameters['navigation'] = $this->getNavigation();

        return $this->render($view, $parameters, $response);
    }

    /**
     * @param string $view
     *
     * @return string
     */
    protected function resolveView(string $view): string
    {
        //remove static template inheritance prefix
        if (strpos($view, '@') === 0) {
            $view = explode('/', $view);
            array_shift($view);
            $view = implode('/', $view);
        }

        return $this->get(TemplateFinder::class)->find($view, true);
    }

    /**
     * @return Navigation
     */
    private function getNavigation(): Navigation
    {
        $context = $this->get(StorefrontContextService::class)->getShopContext();
        $navigationId = $this->get('request_stack')->getCurrentRequest()->attributes->get('active_category_id');

        return $this->get(NavigationService::class)->load($navigationId, $context);
    }
}
