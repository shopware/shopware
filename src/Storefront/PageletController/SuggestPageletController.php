<?php declare(strict_types=1);

namespace Shopware\Storefront\PageletController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Pagelet\Suggest\SuggestPageletLoader;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SuggestPageletController extends StorefrontController
{
    /**
     * @var SuggestPageletLoader
     */
    private $suggestPageletLoader;

    public function __construct(SuggestPageletLoader $suggestPageletLoader)
    {
        $this->suggestPageletLoader = $suggestPageletLoader;
    }

    /**
     * @Route("/search/suggest", name="frontend.search.suggest", methods={"GET"})
     *
     * @param CheckoutContext $context
     * @param InternalRequest $request
     *
     * @return Response
     */
    public function suggest(CheckoutContext $context, InternalRequest $request): Response
    {
        $page = $this->suggestPageletLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/index/pagelet.html.twig', ['page' => $page]);
    }
}
