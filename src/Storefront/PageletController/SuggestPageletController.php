<?php

namespace Shopware\Storefront\PageletController;

use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
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

        return $this->renderStorefront('@Storefront/frontend/search/ajax.html.twig', ['page' => $page]);
    }
}