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
use Shopware\Storefront\Navigation\NavigationService;
use Shopware\Storefront\Twig\TemplateFinder;
use Shopware\StorefrontApi\Context\ContextSubscriber;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class StorefrontController extends Controller
{
    /**
     * {@inheritdoc}
     */
    protected function renderStorefront($view, array $parameters = [], Response $response = null): Response
    {
        return $this->render($this->resolveView($view), $parameters, $response);
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

    protected function redirectToRouteAndReturn(string $route, Request $request, array $parameters = [], $status = 302): RedirectResponse
    {
        $default = [
            'redirectTo' => urlencode($request->getRequestUri()),
        ];
        $parameters = array_merge($default, $parameters);

        return $this->redirectToRoute($route, $parameters, $status);
    }

    protected function handleRedirectTo(string $url): RedirectResponse
    {
        $parsedUrl = parse_url(urldecode($url));
        if (array_key_exists('host', $parsedUrl)) {
            throw new \RuntimeException('Absolute URLs are prohibited for the redirectTo parameter.');
        }

        $redirectUrl = $parsedUrl['path'];
        if (array_key_exists('query', $parsedUrl)) {
            $redirectUrl .= '?' . $parsedUrl['query'];
        }

        if (array_key_exists('fragment', $parsedUrl)) {
            $redirectUrl .= '#' . $parsedUrl['query'];
        }

        return $this->redirect($redirectUrl);
    }
}
