<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Config\ConfigServiceInterface;
use Shopware\Storefront\Exception\MinimumSearchTermLengthNotGiven;
use Shopware\Storefront\Page\Search\SearchPageLoader;
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
        $config = $this->configService->getByShop(
            $context->getShop()->getUuid(),
            $context->getShop()->getParentUuid()
        );

        if (empty($searchTerm) || strlen($searchTerm) < (int) $config['minsearchlenght']) {
            // ToDo: Catch in frontend error handling.
            throw new MinimumSearchTermLengthNotGiven(
                sprintf('Minimum search term length of %d not given.', (int) $config['minsearchlenght'])
            );
        }

        $listing = $this->searchPageLoader->load($searchTerm, $request, $context);

        return $this->renderStorefront(
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

        return $this->renderStorefront(
            '@Storefront/frontend/search/ajax.html.twig',
            [
                'listing' => $this->searchPageLoader->load($searchTerm, $request, $context),
                'searchTerm' => $searchTerm,
            ]
        );
    }
}
