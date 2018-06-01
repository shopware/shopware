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

use Shopware\Checkout\CustomerContext;
use Shopware\Storefront\Page\Listing\ListingPageLoader;
use Shopware\Storefront\Page\Listing\ListingPageRequest;
use Symfony\Component\Routing\Annotation\Route;

class ListingController extends StorefrontController
{
    /**
     * @var ListingPageLoader
     */
    private $listingPageLoader;

    public function __construct(ListingPageLoader $listingPageLoader)
    {
        $this->listingPageLoader = $listingPageLoader;
    }

    /**
     * @Route("/listing/{id}", name="listing_page", options={"seo"=true})
     */
    public function index(string $id, CustomerContext $context, ListingPageRequest $request)
    {
        $request->setNavigationId($id);

        $listingPage = $this->listingPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/frontend/listing/index.html.twig', [
            'listing' => $listingPage,
        ]);
    }
}
