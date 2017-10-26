<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\ShopContext;
use Shopware\Storefront\Exception\MinimumSearchTermLengthNotGiven;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends StorefrontController
{
    /**
     * @Route("/search", name="search_index", options={"seo"=true})
     *
     * @param ShopContext $context
     * @param Request     $request
     *
     * @throws MinimumSearchTermLengthNotGiven
     *
     * @return Response
     */
    public function indexAction(ShopContext $context, Request $request): Response
    {
        $searchTerm = $request->get('search');
        $config = $this->get('shopware.config.cached_config_service')->getByShop(
            $context->getShop()->getUuid(),
            $context->getShop()->getParentUuid()
        );

        if (empty($searchTerm) || strlen($searchTerm) < (int) $config['minsearchlenght']) {
            // ToDo: Catch in frontend error handling.
            throw new MinimumSearchTermLengthNotGiven(
                sprintf('Minimum search term length of %d not given.', (int) $config['minsearchlenght'])
            );
        }

        /** @var SearchPageLoader $searchPageLoader */
        $searchPageLoader = $this->get('shopware.storefront.page.search.search_page_loader');
        $listing = $searchPageLoader->load($searchTerm, $request, $context);

        return $this->render(
            '@Storefront/frontend/search/index.html.twig',
            [
                'listing' => $listing,
                'productBoxLayout' => $listing->getProductBoxLayout(),
                'searchTerm' => $searchTerm,
            ]
        );
    }

    /**
     * @Route("/suggestSearch", name="search_ajax")
     *
     * @param ShopContext $context
     * @param Request     $request
     *
     * @return Response
     */
    public function ajaxAction(ShopContext $context, Request $request): Response
    {
        $searchTerm = $request->get('search');

        if (empty($searchTerm)) {
            return $this->render('');
        }

        /** @var SearchPageLoader $searchPageLoader */
        $searchPageLoader = $this->get('shopware.storefront.page.search.search_page_loader');

        return $this->render(
            '@Storefront/frontend/search/ajax.html.twig',
            [
                'listing' => $searchPageLoader->load($searchTerm, $request, $context),
                'searchTerm' => $searchTerm,
            ]
        );
    }
}
