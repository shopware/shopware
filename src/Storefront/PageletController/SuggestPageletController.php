<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Framework\Page\PageLoaderInterface;
use Shopware\Storefront\Pagelet\Suggest\SuggestPageletLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SuggestPageletController extends StorefrontController
{
    /**
     * @var SuggestPageletLoader|PageLoaderInterface
     */
    private $suggestPageletLoader;

    public function __construct(PageLoaderInterface $suggestPageletLoader)
    {
        $this->suggestPageletLoader = $suggestPageletLoader;
    }

    /**
     * @Route("/search/suggest", name="frontend.search.suggest", methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function suggest(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->suggestPageletLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/base/header/search-suggest.html.twig', ['page' => $page]);
    }
}
