<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Config\ConfigServiceInterface;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Page\Search\SearchPageRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchController extends StorefrontController
{
    /**
     * @var ConfigServiceInterface
     */
    private $configService;

    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    public function __construct(ConfigServiceInterface $configService, SearchPageLoader $searchPageLoader)
    {
        $this->configService = $configService;
        $this->searchPageLoader = $searchPageLoader;
    }

    /**
     * @Route("/search", name="search_index", options={"seo"=false})
     *
     * @return Response
     */
    public function index(StorefrontContext $context, SearchPageRequest $request): Response
    {
        $request->setNavigationId(Defaults::ROOT_CATEGORY);

        $listing = $this->searchPageLoader->load($request, $context);

        return $this->renderStorefront(
            '@Storefront/frontend/search/index.html.twig',
            [
                'listing' => $listing,
                'productBoxLayout' => $listing->getProductBoxLayout(),
                'searchTerm' => $request->getSearchTerm(),
            ]
        );
    }

    /**
     * @Route("/suggestSearch", name="search_ajax")
     *
     * @param StorefrontContext $context
     * @param Request           $request
     *
     * @return Response
     */
    public function ajax(StorefrontContext $context, Request $request): Response
    {
        $searchTerm = $request->get('search');

        if (empty($searchTerm)) {
            return $this->render('');
        }

        return $this->renderStorefront(
            '@Storefront/frontend/search/ajax.html.twig',
            [
                'listing' => $this->searchPageLoader->load($request, $context),
                'searchTerm' => $searchTerm,
            ]
        );
    }
}
