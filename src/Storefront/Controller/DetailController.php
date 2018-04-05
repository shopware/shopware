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

use Shopware\Context\Struct\StorefrontContext;
use Shopware\Storefront\Page\Detail\DetailPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DetailController extends StorefrontController
{
    /**
     * @var DetailPageLoader
     */
    private $detailPageLoader;

    public function __construct(DetailPageLoader $detailPageLoader)
    {
        $this->detailPageLoader = $detailPageLoader;
    }

    /**
     * @Route("/detail/{id}", name="detail_page", options={"seo"="true"})
     */
    public function indexAction(string $id, StorefrontContext $context, Request $request)
    {
        $page = $this->detailPageLoader->load($id, $request, $context);

        $xhr = $request->isXmlHttpRequest();
        $template = '@Storefront/frontend/detail/index.html.twig';

        if ($xhr) {
            $template = '@Storefront/frontend/detail/content.html.twig';
        }

        return $this->renderStorefront($template, ['page' => $page,], null, !$xhr);
    }
}
